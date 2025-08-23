<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUserSeeder extends Seeder
{
    public function run(): void
    {
        // Pull from env if present so you don’t hardcode secrets
        $name  = env('DEFAULT_ADMIN_NAME',  'Admin');
        $email = env('DEFAULT_ADMIN_EMAIL', 'admin@example.com');
        $pass  = env('DEFAULT_ADMIN_PASS',  'password');

        // Idempotent: run multiple times without dupes
        User::updateOrCreate(
            ['email' => $email],
            [
                'name'        => $name,
                'password'    => Hash::make($pass), // bcrypt by default
                'is_admin'    => true,
                'is_manager'  => true,              // set as you prefer
                'employee_id' => null,              // or a valid employee id if needed
            ]
        );
    }
}
