<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketType extends Model
{
    use SoftDeletes;

    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    protected $fillable = [
        'product_id', 'name', 'price', 'quota', 'sold', 'reserved',
        'min_per_order', 'max_per_order', 'sale_start', 'sale_end', 'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_start' => 'datetime',
        'sale_end' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function available(): int
    {
        return max(0, $this->quota - $this->sold - $this->reserved);
    }

    public function inSalePeriod(): bool
    {
        return (! $this->sale_start || $this->sale_start->lte(now()))
            && (! $this->sale_end || $this->sale_end->gte(now()));
    }

    /** Sellable right now, ignoring stock. */
    public function isOnSale(): bool
    {
        return $this->status === self::ACTIVE
            && $this->inSalePeriod()
            && $this->product?->isOnSale();
    }
}
