<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Police Stations
            'stations.view',
            'stations.create',
            'stations.update',
            'stations.delete',

            // Categories
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',

            // Incidents
            'incidents.view',
            'incidents.create',
            'incidents.update',
            'incidents.delete',
            'incidents.assign',

            // Reports
            'reports.view',
            'reports.export',

            // Map
            'map.view',
            'map.view-all',

            // Profile
            'profile.view',
            'profile.update',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles with permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions); // Admin has all permissions

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            'stations.view',
            'categories.view',
            'incidents.view',
            'incidents.update',
            'incidents.assign',
            'reports.view',
            'reports.export',
            'map.view',
            'profile.view',
            'profile.update',
        ]);

        $police = Role::firstOrCreate(['name' => 'police', 'guard_name' => 'web']);
        $police->syncPermissions([
            'incidents.view',
            'incidents.update',
            'map.view',
            'profile.view',
            'profile.update',
        ]);

        $citizen = Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
        $citizen->syncPermissions([
            'incidents.view',
            'incidents.create',
            'map.view',
            'profile.view',
            'profile.update',
        ]);

        // New roles for command hierarchy
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
}
