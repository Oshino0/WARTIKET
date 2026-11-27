<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';
    public const REFUNDED = 'refunded';
    public const LABELS = [
        self::PENDING => 'Menunggu',
        self::PROCESSING => 'Diproses',
        self::PAID => 'Dibayar',
        self::FAILED => 'Gagal',
        self::EXPIRED => 'Kedaluwarsa',
        self::REFUNDED => 'Dikembalikan',
    ];
    protected $fillable = [
        'order_id', 'reference_id', 'transaction_id', 'session_id', 'payment_url', 'provider',
        'amount', 'payment_method', 'status', 'paid_at', 'expires_at',
        'callback_payload', 'needs_review', 'review_note',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'callback_payload' => 'array',
        'needs_review' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }
}
