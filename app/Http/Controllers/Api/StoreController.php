<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = User::where('roles', User::SELLER)
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_livestreaming')->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List Store',
            'data' => $stores,
        ]);
    }

    /**
     * Produk aktif sebuah toko. Dulu produk nonaktif ikut tampil dan ID yang bukan toko tetap dilayani.
     */
    public function productByStore(int $id): JsonResponse
    {
        abort_unless(User::where('roles', User::SELLER)->whereKey($id)->exists(), 404, 'Toko tidak ditemukan.');

        $products = Product::where('seller_id', $id)->where('is_active', true)->latest()->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List Product by Store',
            'data' => $products,
        ]);
    }

    public function livestreaming(): JsonResponse
    {
        $stores = User::where('roles', User::SELLER)->where('is_livestreaming', true)->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List Store Livestreaming',
            'data' => $stores,
        ]);
    }

    /**
     * Ringkasan dashboard penjual. Dulu angka di app ditulis statis (60 produk, 120 order, "May 9, 2024").
     */
    public function summary(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;
        $sold = Order::where('seller_id', $sellerId)->whereIn('status', [Order::PAID, Order::SHIPPING]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_products' => Product::where('seller_id', $sellerId)->where('is_active', true)->count(),
                'total_orders' => (clone $sold)->count(),
                'items_sold' => (int) OrderItem::whereIn('order_id', (clone $sold)->select('id'))->sum('quantity'),
                'revenue' => (int) (clone $sold)->sum('total_price'),
                'revenue_this_month' => (int) (clone $sold)->where('paid_at', '>=', now()->startOfMonth())->sum('total_price'),
                'to_ship' => Order::where('seller_id', $sellerId)->where('status', Order::PAID)->count(),
                'awaiting_payment' => Order::where('seller_id', $sellerId)->where('status', Order::PENDING)->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function setLivestreaming(Request $request): JsonResponse
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $request->user()->update(['is_livestreaming' => $data['is_active']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Store is livestreaming',
        ]);
    }
}
