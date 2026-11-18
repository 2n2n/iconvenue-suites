<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive and nullable: existing bookings keep NULLs, which the code reads as
 * "Regular rate" with the stored total_amount untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('booking_platform_id')->nullable()->after('package_id')
                ->constrained('booking_platforms')->nullOnDelete();
            $table->decimal('rate_per_night', 10, 2)->nullable()->after('booking_platform_id');
            $table->string('platform_reference', 100)->nullable()->after('rate_per_night');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['booking_platform_id']);
            $table->dropColumn(['booking_platform_id', 'rate_per_night', 'platform_reference']);
        });
    }
};
