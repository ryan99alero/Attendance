<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define permissions
        $permissions = [
            // Attendance
            'view attendance',
            'create attendance',
            'edit attendance',
            'delete attendance',

            // Employees
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',

            // Admin panel
            'access admin panel',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        $admin   = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $viewer  = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        // Assign all permissions to admin
        $admin->syncPermissions(Permission::all());

        // Manager role
        $manager->syncPermissions([
            'view attendance',
            'create attendance',
            'edit attendance',
            'view employees',
        ]);

        // Viewer role
        $viewer->syncPermissions([
            'view attendance',
            'view employees',
        ]);
    }
}
