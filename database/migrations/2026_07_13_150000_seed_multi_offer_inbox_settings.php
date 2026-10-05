<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'name' => 'enable_multi_offer_inbox',
                'field' => 'select',
                'category' => 'trip_settings',
                'value' => '0',
                'option_value' => null,
                'group_name' => null,
            ],
            [
                'name' => 'max_concurrent_pending_offers_per_driver',
                'field' => 'text',
                'category' => 'trip_settings',
                'value' => '3',
                'option_value' => null,
                'group_name' => null,
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('settings')->where('name', $setting['name'])->exists();
            if (! $exists) {
                DB::table('settings')->insert(array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('name', [
            'enable_multi_offer_inbox',
            'max_concurrent_pending_offers_per_driver',
        ])->delete();
    }
};
