<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            UPDATE driver_availabilities
            SET duration = CASE
                WHEN online_at IS NULL THEN GREATEST(duration, 0)
                WHEN offline_at IS NULL THEN GREATEST(duration, 0)
                WHEN offline_at >= online_at THEN ROUND(TIMESTAMPDIFF(SECOND, online_at, offline_at) / 60, 2)
                ELSE 0
            END
            WHERE duration < 0
               OR duration IS NULL
               OR online_at IS NOT NULL
               OR offline_at IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This migration normalizes historical data only.
    }
};
