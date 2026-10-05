<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRequestPricingAuditsTable extends Migration
{
    public function up()
    {
        Schema::create('request_pricing_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->index();
            $table->string('phase', 20); // eta | final
            $table->json('audit');
            $table->timestamps();

            $table->unique(['request_id', 'phase']);
            $table->foreign('request_id')->references('id')->on('requests')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('request_pricing_audits');
    }
}
