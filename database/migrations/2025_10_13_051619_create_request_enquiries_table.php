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
        Schema::create('request_enquiries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->double('pick_lat', 15, 8)->nullable();
            $table->double('pick_lng', 15, 8)->nullable();
            $table->double('drop_lat', 15, 8)->nullable();
            $table->double('drop_lng', 15, 8)->nullable();
            $table->string('pick_address')->nullable();
            $table->string('drop_address')->nullable();
            $table->boolean('converted_as_ride')->default(0);
            $table->boolean('is_cancelled')->default(0);
             $table->unsignedInteger('dispatcher_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('dispatcher_id')
            ->references('id')
            ->on('users')
            ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_enquiries');
    }
};
