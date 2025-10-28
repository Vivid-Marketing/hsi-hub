<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignSuperAdminRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-super-admin {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign super-admin role to a user by email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        // Find the user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }
        
        // Check if super-admin role exists
        $superAdminRole = Role::where('name', 'super-admin')->first();
        
        if (!$superAdminRole) {
            $this->error("Super-admin role not found. Please run the RolePermissionSeeder first.");
            return 1;
        }
        
        // Assign the role
        $user->assignRole('super-admin');
        
        $this->info("Successfully assigned super-admin role to {$user->name} ({$user->email})");
        
        // Show current roles
        $roles = $user->getRoleNames();
        $this->info("Current roles: " . $roles->implode(', '));
        
        return 0;
    }
}