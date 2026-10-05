<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'dispatch-monitor-view'],
            [
                'name' => 'dispatch-monitor-view',
                'description' => 'dispatch monitor searching rides debugger',
                'main_menu' => 'dispatch-monitor',
                'sub_menu' => null,
                'main_link' => 'dispatch-monitor-view',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Attach to roles that already can view ongoing rides, plus locked admin roles.
        $roleIds = Role::query()
            ->where(function ($q) {
                $q->where('all', true)
                    ->orWhere('locked', true)
                    ->orWhereIn('slug', ['admin', 'super-admin', 'owner', 'franchise-owner']);
            })
            ->orWhereHas('permissions', function ($q) {
                $q->where('slug', 'ongoing-request-view');
            })
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permission->id)
                ->exists();
            if (! $exists) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permission = Permission::where('slug', 'dispatch-monitor-view')->first();
        if ($permission) {
            DB::table('permission_role')->where('permission_id', $permission->id)->delete();
            $permission->delete();
        }
    }
};
