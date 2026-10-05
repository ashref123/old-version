<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS GetDriverOnlineOfflineDuration');

        DB::unprepared('
            CREATE PROCEDURE GetDriverOnlineOfflineDuration(
                IN p_driver_id INT,
                IN p_from_date DATE,
                IN p_to_date DATE
            )
            BEGIN
                SELECT
                    DATE(da.online_at) AS date,
                    d.name AS driver_name,
                    SUM(GREATEST(da.duration, 0)) / 60 AS total_duration_hours
                FROM
                    driver_availabilities da
                JOIN
                    drivers d ON da.driver_id = d.id
                WHERE
                    da.driver_id = p_driver_id
                    AND DATE(da.online_at) BETWEEN p_from_date AND p_to_date
                GROUP BY
                    DATE(da.online_at), d.name;
            END
        ');
    }

    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS GetDriverOnlineOfflineDuration');
    }
};
