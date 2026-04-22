<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Console\Command;

class SyncPostsCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:sync-count {--user_id= : Sync count for specific user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync posts count for user profiles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user_id');

        if ($userId) {
            // Sync for specific user
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }

            if (!$user->profile) {
                $this->error("User {$userId} does not have a profile.");
                return 1;
            }

            $actualCount = $user->posts()->count();
            $user->profile->update(['posts_count' => $actualCount]);

            $this->info("Synced posts count for user {$user->name}: {$actualCount}");
        } else {
            // Sync for all users
            $this->info('Syncing posts count for all users...');

            $profiles = Profile::all();
            $bar = $this->output->createProgressBar($profiles->count());

            foreach ($profiles as $profile) {
                $actualCount = $profile->getActualPostsCount();
                $profile->update(['posts_count' => $actualCount]);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Posts count sync completed for all users!');
        }

        return 0;
    }
}
