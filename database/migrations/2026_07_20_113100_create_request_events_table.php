<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRequestEventsTable extends Migration
{
    public function up()
    {
        Schema::create('request_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->index();
            $table->string('event', 80)->index();
            $table->string('actor_type', 40)->nullable();
            $table->string('actor_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('requests')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('request_events');
    }
}
