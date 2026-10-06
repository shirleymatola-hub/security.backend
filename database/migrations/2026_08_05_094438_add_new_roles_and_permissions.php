<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Add district_id to users table for District Commander
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('district_id')->nullable()->after('police_station_id')->constrained()->nullOnDelete();
        });

        // Add new permissions
        $newPermissions = [
            'incidents.forward',
            'incidents.view-station',
            'incidents.view-squad',
            'incidents.view-district',
            'users.view-station',
            'users.view-squad',
            'users.view-district',
            'reports.view-station',
            'reports.view-squad',
            'reports.view-district',
        ];

        foreach ($newPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create new roles
        $postCommander = Role::firstOrCreate(['name' => 'post_commander', 'guard_name' => 'web']);
        $postCommander->syncPermissions([
            'incidents.view',
            'incidents.view-station',
            'incidents.update',
            'incidents.assign',
            'reports.view',
            'reports.view-station',
            'reports.export',
            'map.view',
            'users.view',
            'users.view-station',
            'profile.view',
            'profile.update',
        ]);

        $squadCommander = Role::firstOrCreate(['name' => 'squad_commander', 'guard_name' => 'web']);
        $squadCommander->syncPermissions([
            'incidents.view',
            'incidents.view-station',
            'incidents.view-squad',
            'incidents.update',
            'incidents.assign',
            'reports.view',
            'reports.view-station',
            'reports.view-squad',
            'reports.export',
            'map.view',
            'users.view',
            'users.view-station',
            'users.view-squad',
            'stations.view',
            'profile.view',
            'profile.update',
        ]);

        $districtCommander = Role::firstOrCreate(['name' => 'district_commander', 'guard_name' => 'web']);
        $districtCommander->syncPermissions([
            'incidents.view',
            'incidents.view-station',
            'incidents.view-squad',
            'incidents.view-district',
            'incidents.update',
            'incidents.assign',
            'reports.view',
            'reports.view-station',
            'reports.view-squad',
            'reports.view-district',
            'reports.export',
            'map.view',
            'map.view-all',
            'users.view',
            'users.view-station',
            'users.view-squad',
            'users.view-district',
            'stations.view',
            'categories.view',
            'profile.view',
            'profile.update',
        ]);

        $sernicOfficer = Role::firstOrCreate(['name' => 'sernic_officer', 'guard_name' => 'web']);
        $sernicOfficer->syncPermissions([
            'incidents.view',
            'incidents.forward',
            'map.view',
            'profile.view',
            'profile.update',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropColumn('district_id');
        });

        Role::whereIn('name', ['post_commander', 'squad_commander', 'district_commander', 'sernic_officer'])->delete();
        Permission::whereIn('name', [
            'incidents.forward', 'incidents.view-station', 'incidents.view-squad', 'incidents.view-district',
            'users.view-station', 'users.view-squad', 'users.view-district',
            'reports.view-station', 'reports.view-squad', 'reports.view-district',
        ])->delete();
    }
};
