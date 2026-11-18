<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suite_platform_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues_and_suites')->cascadeOnDelete();
            $table->foreignId('booking_platform_id')->constrained('booking_platforms')->cascadeOnDelete();
            $table->decimal('price_per_night', 10, 2);
            $table->timestamps();

            $table->unique(['venue_id', 'booking_platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suite_platform_rates');
    }
};
