<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Midtrans\CallbackService;
use Illuminate\Http\Request;
use App\Models\User;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class CallbackController extends Controller
{
    //callback
    public function callback(Request $request) {
        $callback = new CallbackService();

        $order = $callback->getOrder();
        if ($callback->isSuccess()) {
            $order->update([
                'status' => 'paid',
            ]);
            $this->sendNotificationToUser($order->seller_id, 'Ada order baru masuk senilai Rp ' . number_format($order->grand_total));
        } else if ($callback->isExpire()) {
            $order->update([
                'status' => 'expired',
            ]);
        } else if ($callback->isCancelled()) {
            $order->update([
                'status' => 'cancelled',
            ]);
        }
        return response()->json([
            'meta' => [
                'code' => 200,
                'message' => 'Callback success',
            ],
        ]);
    }

    public function sendNotificationToUser($userId, $message)
    {
        // Dapatkan FCM token user dari tabel 'users'

        $user = User::find($userId);
        $token = $user->fcm_token;

        // Kirim notifikasi ke perangkat Android
        $messaging = app('firebase.messaging');
        $notification = Notification::create('Pesanan Baru', $message);

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification);

        $messaging->send($message);
    }
}
