<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Midtrans\CallbackService;
use Illuminate\Http\Request;

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
}
