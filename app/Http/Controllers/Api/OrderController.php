<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Payment\VirtualAccountService;
use App\Services\PushNotifier;
use App\Services\Shipping\ShippingSimulator;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderController extends Controller
{
    /** Berat per paket yang dipakai app saat menampilkan ongkir (gram). */
    private const PARCEL_WEIGHT = 1000;

    public function __construct(
        private ShippingSimulator $shipping,
        private VirtualAccountService $virtualAccounts,
        private PushNotifier $notifier,
    ) {}

    /**
     * Buat order dari keranjang satu toko. Dulu harga produk, ongkir, alamat, dan stok tidak diperiksa:
     * ongkir diambil dari app, alamat/produk milik orang lain diterima, stok tidak berkurang, produk yang
     * tidak ada membuat 500, dan nomor transaksi `TRX-time()` bisa bentrok.
     */
    public function createOrder(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')->where('user_id', $user->id)],
            'seller_id' => ['required', 'integer', Rule::exists('users', 'id')->where('roles', 'seller')],
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'shipping_service' => 'required|string|max:30',
            'bank_va_name' => ['required', 'string', Rule::in(VirtualAccountService::BANKS)],
        ], [
            'address_id.exists' => 'Alamat pengiriman tidak ditemukan.',
            'seller_id.exists' => 'Toko tidak ditemukan.',
            'bank_va_name.in' => 'Bank virtual account tidak didukung.',
        ]);

        $seller = User::findOrFail($data['seller_id']);
        $address = Address::findOrFail($data['address_id']);
        [$courier, $service] = $this->parseShippingService($data['shipping_service']);
        $shippingPrice = $this->shipping->priceFor($seller->district, $address->district, $courier, $service, self::PARCEL_WEIGHT);

        if ($shippingPrice === null) {
            throw ValidationException::withMessages(['shipping_service' => 'Layanan pengiriman tidak tersedia untuk alamat ini.']);
        }

        $order = DB::transaction(function () use ($data, $user, $seller, $address, $courier, $service, $shippingPrice) {
            $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
                ->lockForUpdate()->get()->keyBy('id');

            $total = 0;
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || $product->seller_id !== $seller->id || ! $product->is_active) {
                    throw ValidationException::withMessages(['items' => 'Ada produk yang tidak tersedia di toko ini.']);
                }
                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => "Stok {$product->name} tinggal {$product->stock}."]);
                }
                $total += (int) $product->price * $item['quantity'];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $address->id,
                'seller_id' => $seller->id,
                'shipping_price' => $shippingPrice,
                'shipping_service' => strtoupper("{$courier} {$service}"),
                'shipping_courier' => $courier,
                'status' => Order::PENDING,
                'total_price' => $total,
                'grand_total' => $total + $shippingPrice,
                'transaction_number' => 'TRX-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4)),
                'payment_va_name' => $data['bank_va_name'],
            ]);

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'price' => $product->price,
                    'quantity' => $item['quantity'],
                ]);
                $product->decrement('stock', $item['quantity']);
            }

            return $order;
        });

        try {
            $order->update(['payment_va_number' => $this->virtualAccounts->create($order)]);
        } catch (RuntimeException $e) {
            Log::error('Pembuatan VA gagal', ['order' => $order->id, 'error' => $e->getMessage()]);
            $order->closeUnpaid(Order::CANCELLED);

            return response()->json(['status' => 'error', 'message' => 'Pembayaran belum bisa dibuat, coba lagi.'], 502);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Order created',
            // Nilai uang tetap angka seperti respons lama (app membaca int); fresh() akan mengembalikan string desimal.
            'data' => $this->withPaymentMode($order),
        ], 201);
    }

    /**
     * Konfirmasi bayar dalam mode simulasi (tanpa MIDTRANS_SERVER_KEY), pengganti simulator Midtrans.
     */
    public function simulatePayment(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($this->virtualAccounts->isSandbox(), 403, 'Simulasi pembayaran hanya untuk mode uji.');

        if ($order->markPaid()) {
            $this->notifier->send($order->seller, 'Pesanan Baru', 'Ada order baru masuk senilai Rp '.number_format($order->grand_total, 0, ',', '.'));
        }

        return response()->json(['status' => $order->fresh()->status]);
    }

    /**
     * Input resi oleh penjual. Dulu penjual mana pun bisa mengubah order mana pun (dan order yang belum
     * dibayar ikut berubah menjadi "shipping").
     */
    public function updateShippingNumber(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->seller_id === $request->user()->id, 404);
        $data = $request->validate([
            'shipping_number' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,30}$/'],
        ], ['shipping_number.regex' => 'Nomor resi 8–30 karakter huruf/angka.']);

        try {
            $order->ship($data['shipping_number']);
        } catch (DomainException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $this->notifier->send($order->user, 'Paket Dikirim', 'Paket dengan nomor '.$order->shipping_number.' telah dikirim');

        return response()->json([
            'status' => 'success',
            'message' => 'Shipping number updated',
            'data' => $order->fresh(),
        ]);
    }

    public function historyOrderBuyer(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('seller:id,name,photo', 'orderItems.product')
            ->latest()->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List History Order Buyer',
            'data' => $orders,
        ]);
    }

    public function historyOrderSeller(Request $request): JsonResponse
    {
        $orders = Order::where('seller_id', $request->user()->id)
            ->with('user:id,name,email,phone', 'orderItems.product', 'address')
            ->latest()->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List History Order Seller',
            'data' => $orders,
        ]);
    }

    public function checkOrderStatus(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->isParticipant($request->user()), 404);

        return response()->json(['status' => $order->status]);
    }

    public function getOrderById(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->isParticipant($request->user()), 404);

        return response()->json([
            'status' => 'success',
            'message' => 'Order',
            'data' => $this->withPaymentMode($order->load('orderItems.product', 'address.user', 'seller:id,name,photo')),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseShippingService(string $value): array
    {
        $parts = preg_split('/\s+/', strtolower(trim($value)));
        $courier = $parts[0] ?? 'jne';
        $service = strtoupper($parts[1] ?? 'REG');

        return [$courier, $service];
    }

    private function withPaymentMode(Order $order): Order
    {
        return $order->setAttribute('payment_mode', $this->virtualAccounts->isSandbox() ? 'sandbox' : 'midtrans')
            ->setAttribute('payment_expired_at', $order->created_at?->copy()->addHours(Order::PAYMENT_WINDOW_HOURS));
    }
}
