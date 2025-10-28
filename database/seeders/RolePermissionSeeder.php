<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'view-dashboard',
            'manage-courses',
            'view-courses',
            'create-courses',
            'edit-courses',
            'delete-courses',
            'manage-pdf-tools',
            'view-pdf-tools',
            'create-pdf',
            'manage-users',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Assign permissions to roles
        $superAdminRole->givePermissionTo(Permission::all());
        $adminRole->givePermissionTo(Permission::all());
        
        $managerRole->givePermissionTo([
            'view-dashboard',
            'manage-courses',
            'view-courses',
            'create-courses',
            'edit-courses',
            'delete-courses',
            'manage-pdf-tools',
            'view-pdf-tools',
            'create-pdf',
        ]);

        $userRole->givePermissionTo([
            'view-dashboard',
            'view-courses',
            'view-pdf-tools',
        ]);
    }
}