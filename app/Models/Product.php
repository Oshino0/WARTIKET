<?php

namespace App\Models;

use App\Models\Concerns\GeneratesSlug;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes, GeneratesSlug;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published'; // visible and on sale
    public const CLOSED = 'closed';       // visible, sales closed
    public const STATUSES = [
        self::DRAFT => 'Draft',
        self::PUBLISHED => 'Dipublikasikan',
        self::CLOSED => 'Penjualan ditutup',
    ];

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'image', 'gallery',
        'location', 'metadata', 'start_at', 'end_at', 'status', 'is_featured',
    ];
    protected $casts = [
        'metadata' => 'array',
        'gallery' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_featured' => 'boolean',
        'email_template' => 'array',
    ];

    public static function findByUrlIdentifier(string $identifier): self
    {
        if (ctype_digit($identifier)) {
            $product = static::find($identifier);
            if ($product) {
                return $product;
            }
        }

        return static::where('slug', $identifier)->firstOrFail();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /** Products customers can see in the catalog. */
    public function scopeVisible($query)
    {
        return $query->whereIn('status', [self::PUBLISHED, self::CLOSED]);
    }

    /** Hide events that have already ended. */
    public function scopeUpcoming($query)
    {
        return $query->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()));
    }

    public function isOnSale(): bool
    {
        return $this->status === self::PUBLISHED
            && (! $this->end_at || $this->end_at->isFuture());
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? Storage::disk('public')->url($this->image) : null);
    }

    /** Lowest price among active ticket types (eager-load ticketTypes to avoid N+1). */
    protected function startingPrice(): Attribute
    {
        return Attribute::get(
            fn () => $this->ticketTypes->where('status', TicketType::ACTIVE)->min('price')
        );
    }
}
