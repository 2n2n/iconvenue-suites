<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Production never runs db:seed, so the default platforms ship with the migration.
        // Idempotent: only inserts names that are not there yet.
        $now = now();
        foreach (['Agoda' => 1, 'Booking.com' => 2] as $name => $order) {
            if (! DB::table('booking_platforms')->where('name', $name)->exists()) {
                DB::table('booking_platforms')->insert([
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_platforms');
    }
};
