<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-email {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Brevo email configuration by sending a test email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? 'zayedbaloch@gmail.com';

        $this->info("Sending test email to: {$email}");

        try {
            Mail::raw('This is a test email from your MedApp Laravel application using Brevo SMTP. If you received this, your email configuration is working correctly!', function ($message) use ($email) {
                $message->to($email)
                        ->subject('MedApp - Brevo Email Test');
            });

            $this->info('✅ Test email sent successfully!');
            $this->info("Check {$email} for the test message.");

        } catch (\Exception $e) {
            $this->error('❌ Failed to send test email:');
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
