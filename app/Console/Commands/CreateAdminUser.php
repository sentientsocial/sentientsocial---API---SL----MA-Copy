<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin-user {name?} {email?} {password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin user for the application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name') ?? $this->ask('Enter admin name');
        $email = $this->argument('email') ?? $this->ask('Enter admin email');
        $password = $this->argument('password') ?? $this->secret('Enter admin password');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password)
        ]);

        $username = strtolower(str_replace(' ', '.', $name));
        
        Profile::create([
            'user_id' => $user->id,
            'username' => $username,
            'display_name' => $name,
            'meditation_minutes' => 0,
            'streak_count' => 0,
        ]);

        $this->info('Admin user created successfully!');
        $this->newLine();
        $this->info('Email: ' . $email);
        $this->info('Username: ' . $username);
    }
}
