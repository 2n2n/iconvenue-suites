<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuitePlatformRate extends Model
{
    protected $fillable = ['venue_id', 'booking_platform_id', 'price_per_night'];

    protected $casts = [
        'price_per_night' => 'decimal:2',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(BookingPlatform::class, 'booking_platform_id');
    }
}
