<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $fillable = [
        'title', 'image', 'link_url', 'sort_order', 'image_position_x', 'image_position_y', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'image_position_x' => 'integer',
        'image_position_y' => 'integer',
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->image));
    }
}
