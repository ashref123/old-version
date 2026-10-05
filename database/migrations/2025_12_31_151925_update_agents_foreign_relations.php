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

        if (Schema::hasTable('wallet_withdrawal_requests')) {
            if (Schema::hasColumn('wallet_withdrawal_requests', 'agent_id')) {
                Schema::table('wallet_withdrawal_requests', function (Blueprint $table) {

                    $foreignKeyExists = DB::select(
                        "SELECT CONSTRAINT_NAME 
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                        WHERE TABLE_NAME = 'agents' 
                        AND COLUMN_NAME = 'agent_id' 
                        AND CONSTRAINT_SCHEMA = DATABASE()"
                    );
                    if(!$foreignKeyExists){
                    $table->foreign('agent_id')
                            ->references('id')
                            ->on('agents')
                            ->onDelete('cascade');
                    }
                    
                });
            }
        } 
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
