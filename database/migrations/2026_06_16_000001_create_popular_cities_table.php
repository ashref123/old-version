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
        Schema::create('popular_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('ride_type', 30);
            $table->string('city_search');
            $table->decimal('lat', 12, 8);
            $table->decimal('lng', 12, 8);
            $table->string('shortcode', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->index(['zone_id', 'ride_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('popular_cities');
    }
};
