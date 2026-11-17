<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client email becomes optional (walk-ins / phone bookings). Existing rows are untouched.
 * See docs/decisions/2026-11-17-optional-client-email.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('client_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Bookings created without an email would block NOT NULL; store them as empty strings first.
        DB::table('bookings')->whereNull('client_email')->update(['client_email' => '']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('client_email')->nullable(false)->change();
        });
    }
};
