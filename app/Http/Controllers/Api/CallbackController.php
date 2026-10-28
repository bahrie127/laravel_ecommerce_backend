<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    /**
     * Notifikasi Midtrans. Dulu signature tidak pernah diperiksa (fungsi verifikasi memakai kolom `number`
     * yang tidak ada), order tidak ditemukan membuat 500, dan notifikasi FCM yang gagal ikut menggagalkan
     * callback sehingga Midtrans mengulang terus.
     */
    public function callback(Request $request, PushNotifier $notifier): JsonResponse
    {
        $serverKey = (string) config('midtrans.server_key');
        $orderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');
        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if ($serverKey === '' || ! hash_equals($expected, (string) $request->input('signature_key'))) {
            return response()->json(['meta' => ['code' => 403, 'message' => 'Invalid signature']], 403);
        }

        $order = Order::where('transaction_number', $orderId)->first();
        if (! $order) {
            return response()->json(['meta' => ['code' => 404, 'message' => 'Order not found']], 404);
        }

        if ((int) round((float) $grossAmount) !== (int) $order->grand_total) {
            return response()->json(['meta' => ['code' => 422, 'message' => 'Amount mismatch']], 422);
        }

        $transactionStatus = $request->input('transaction_status');
        $fraudOk = in_array($request->input('fraud_status'), [null, '', 'accept'], true);

        if ($statusCode === '200' && $fraudOk && in_array($transactionStatus, ['capture', 'settlement'], true)) {
            if ($order->markPaid()) {
                $notifier->send($order->seller, 'Pesanan Baru', 'Ada order baru masuk senilai Rp '.number_format($order->grand_total, 0, ',', '.'));
            }
        } elseif ($transactionStatus === 'expire') {
            $order->closeUnpaid(Order::EXPIRED);
        } elseif (in_array($transactionStatus, ['cancel', 'deny'], true)) {
            $order->closeUnpaid(Order::CANCELLED);
        }

        return response()->json(['meta' => ['code' => 200, 'message' => 'Callback success']]);
    }
}
