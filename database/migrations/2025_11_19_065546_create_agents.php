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

         if(!Schema::hasTable('agents')){
            Schema::create('agents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedInteger('user_id');
                $table->uuid('service_location_id')->nullable();
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->text('address')->nullable();
                $table->unsignedInteger('country')->nullable();
                $table->string('state', 50)->nullable();
                $table->string('city', 50)->nullable();
                $table->integer('pincode')->nullable();
                $table->string('email', 150);
                $table->string('mobile', 14)->unique();
                $table->boolean('active')->default(0);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->foreign('service_location_id')
                        ->references('id')
                        ->on('service_locations')
                        ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
