<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-unpaid')]
#[Description('Tandai order belum dibayar lewat batas VA sebagai kedaluwarsa dan kembalikan stoknya')]
class ExpireUnpaidOrders extends Command
{
    public function handle(): int
    {
        $expired = 0;
        Order::where('status', Order::PENDING)
            ->where('created_at', '<', now()->subHours(Order::PAYMENT_WINDOW_HOURS))
            ->each(function (Order $order) use (&$expired) {
                $expired += (int) $order->closeUnpaid(Order::EXPIRED);
            });

        $this->info("{$expired} order kedaluwarsa.");

        return self::SUCCESS;
    }
}
