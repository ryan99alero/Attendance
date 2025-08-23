<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use function Laravel\Prompts\{text, password, confirm};

class ProvisionAdminUser extends Command
{
    protected $signature = 'app:provision-admin';
    protected $description = 'Interactively create or update the initial admin user';

    public function handle(): int
    {
        $email = text('Admin email', required: true, validate: fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email');
        $name  = text('Admin name', default: 'Administrator', required: true);
        $pass  = password('Admin password (won’t echo)', required: true, validate: fn($v) => strlen($v) >= 10 ? null : 'Min 10 chars');
        $confirm = password('Confirm password', required: true);

        if ($pass !== $confirm) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'       => $name,
                'password'   => Hash::make($pass),
                'is_admin'   => true,
                'is_manager' => true,
                'must_change_password' => true, // see Option 3
            ]
        );

        $this->info("Admin user ready: {$user->email}");
        return self::SUCCESS;
    }
}
