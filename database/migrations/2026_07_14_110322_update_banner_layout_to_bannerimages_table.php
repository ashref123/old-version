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
        if (Schema::hasTable('bannersimages')) {
            if (!Schema::hasColumn('bannersimages', 'banner_layout')) {
                Schema::table('bannersimages', function (Blueprint $table) {
                    $table->string('banner_layout')->after('banner_button_text_color')->default('image-left');                    
                });
            }
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bannerimages', function (Blueprint $table) {
            //
        });
    }
};
