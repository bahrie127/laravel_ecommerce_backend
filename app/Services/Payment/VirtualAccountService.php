<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\Midtrans\CreateVAService;
use RuntimeException;
use Throwable;

/**
 * Nomor virtual account untuk order. Dengan `MIDTRANS_SERVER_KEY` memakai Midtrans Core API; tanpa kunci
 * berjalan dalam mode simulasi (nomor VA dibuat lokal dan pembayaran dikonfirmasi lewat endpoint simulasi).
 */
class VirtualAccountService
{
    public const BANKS = ['bca', 'bni', 'bri', 'cimb'];

    /** @var array<string, string> */
    private const SANDBOX_PREFIX = ['bca' => '12345', 'bni' => '8808', 'bri' => '88608', 'cimb' => '1899'];

    public function isSandbox(): bool
    {
        return blank(config('midtrans.server_key'));
    }

    public function create(Order $order): string
    {
        if ($this->isSandbox()) {
            $prefix = self::SANDBOX_PREFIX[$order->payment_va_name] ?? '8808';

            return $prefix.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT).random_int(1000, 9999);
        }

        try {
            $response = (new CreateVAService($order->loadMissing('orderItems.product', 'user')))->getVA();
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal membuat virtual account: '.$e->getMessage(), previous: $e);
        }

        $vaNumber = $response->va_numbers[0]->va_number ?? null;
        if (! $vaNumber) {
            throw new RuntimeException('Midtrans tidak mengembalikan nomor virtual account.');
        }

        return $vaNumber;
    }
}
