<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const PENDING = 'pending';

    public const PENDING_VERIFICATION = 'pending_verification';

    public const PAID = 'paid';

    public const SENT = 'sent';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    public const REFUNDED = 'refunded';

    public const LABELS = [
        self::PENDING => 'Menunggu pembayaran',
        self::PENDING_VERIFICATION => 'Menunggu verifikasi',
        self::PAID => 'Dibayar',
        self::SENT => 'Tiket terkirim',
        self::REJECTED => 'Ditolak',
        self::EXPIRED => 'Kedaluwarsa',
        self::CANCELLED => 'Dibatalkan',
        self::REFUNDED => 'Dana dikembalikan',
    ];

    protected $fillable = [
        'order_number', 'user_id', 'buyer_name', 'buyer_email', 'buyer_phone',
        'subtotal', 'admin_fee', 'total', 'status', 'expires_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }
}
