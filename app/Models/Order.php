<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const SHIPPING = 'shipping';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    /** Batas bayar virtual account (jam). */
    public const PAYMENT_WINDOW_HOURS = 24;

    protected $fillable = [
        'user_id',
        'address_id',
        'seller_id',
        'total_price',
        'shipping_price',
        'grand_total',
        'status',
        'payment_va_name',
        'payment_va_number',
        'shipping_service',
        'shipping_courier',
        'shipping_number',
        'transaction_number',
        'paid_at',
        'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isParticipant(?User $user): bool
    {
        return $user !== null && in_array($user->id, [$this->user_id, $this->seller_id], true);
    }

    /**
     * Tandai lunas. Idempoten: callback yang datang dua kali tidak mengubah apa pun.
     */
    public function markPaid(): bool
    {
        if ($this->status !== self::PENDING) {
            return false;
        }

        $this->update(['status' => self::PAID, 'paid_at' => now()]);

        return true;
    }

    /**
     * Order belum dibayar yang kedaluwarsa/dibatalkan mengembalikan stok yang sudah dipesan.
     */
    public function closeUnpaid(string $status): bool
    {
        if ($this->status !== self::PENDING) {
            return false;
        }

        DB::transaction(function () use ($status) {
            foreach ($this->orderItems()->get() as $item) {
                Product::whereKey($item->product_id)->increment('stock', $item->quantity);
            }
            $this->update(['status' => $status]);
        });

        return true;
    }

    public function ship(string $shippingNumber): void
    {
        if (! in_array($this->status, [self::PAID, self::SHIPPING], true)) {
            throw new DomainException('Resi hanya bisa diisi untuk pesanan yang sudah dibayar.');
        }

        $this->update([
            'shipping_number' => strtoupper($shippingNumber),
            'shipping_courier' => $this->shipping_courier ?: 'jne',
            'status' => self::SHIPPING,
            'shipped_at' => $this->shipped_at ?? now(),
        ]);
    }
}
