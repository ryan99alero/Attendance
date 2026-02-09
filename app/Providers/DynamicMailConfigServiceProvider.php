<?php

namespace App\Providers;

use Exception;
use App\Models\CompanySetup;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class DynamicMailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * Dynamically configure mail settings from CompanySetup if SMTP is enabled.
     */
    public function boot(): void
    {
        // Only run if the table exists (prevents errors during migrations)
        if (!Schema::hasTable('company_setup')) {
            return;
        }

        try {
            $companySetup = CompanySetup::first();

            if (!$companySetup || !$companySetup->smtp_enabled) {
                return;
            }

            // Only override if we have the required settings
            if (empty($companySetup->smtp_host) || empty($companySetup->smtp_from_address)) {
                return;
            }

            // Configure the default mailer to use SMTP
            Config::set('mail.default', 'smtp');

            // Configure SMTP settings
            Config::set('mail.mailers.smtp.host', $companySetup->smtp_host);
            Config::set('mail.mailers.smtp.port', $companySetup->smtp_port ?? 587);
            Config::set('mail.mailers.smtp.username', $companySetup->smtp_username);
            Config::set('mail.mailers.smtp.password', $companySetup->smtp_password); // Already decrypted by model cast
            Config::set('mail.mailers.smtp.timeout', $companySetup->smtp_timeout ?? 30);

            // Handle encryption setting
            $encryption = $companySetup->smtp_encryption;
            if ($encryption === 'none') {
                Config::set('mail.mailers.smtp.encryption', null);
            } else {
                Config::set('mail.mailers.smtp.encryption', $encryption);
            }

            // SSL verification
            if (!$companySetup->smtp_verify_peer) {
                Config::set('mail.mailers.smtp.stream', [
                    'ssl' => [
                        'allow_self_signed' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);
            }

            // Configure from address
            Config::set('mail.from.address', $companySetup->smtp_from_address);
            Config::set('mail.from.name', $companySetup->smtp_from_name ?? config('app.name'));

            // Configure reply-to if set
            if (!empty($companySetup->smtp_reply_to)) {
                Config::set('mail.reply_to.address', $companySetup->smtp_reply_to);
                Config::set('mail.reply_to.name', $companySetup->smtp_from_name ?? config('app.name'));
            }

        } catch (Exception $e) {
            // Silently fail - don't break the app if DB is not ready
            report($e);
        }
    }
}
