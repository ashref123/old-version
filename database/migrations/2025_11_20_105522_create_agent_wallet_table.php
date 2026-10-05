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

        if(!Schema::hasTable('agent_wallet')){
            Schema::create('agent_wallet', function (Blueprint $table) {
            $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->double('amount_added', 10, 2)->default(0);
                $table->double('amount_balance', 10, 2)->default(0);
                $table->double('amount_spent', 10, 2)->default(0);
                $table->timestamps();

                $table->foreign('user_id')
                        ->references('id')
                        ->on('agents')
                        ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_wallet');
    }
};
