<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('package_addon_inclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_package_id')->constrained('venue_packages')->onDelete('cascade');
            $table->foreignId('venue_addon_id')->constrained('venue_addons')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['venue_package_id', 'venue_addon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_addon_inclusions');
    }
};
