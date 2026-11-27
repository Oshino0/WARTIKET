<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = ['order_item_id', 'ticket_code', 'qr_token', 'status', 'checked_in_at', 'checked_in_by'];

    protected $casts = ['checked_in_at' => 'datetime'];

    protected $hidden = ['qr_token'];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(TicketScan::class);
    }
}
