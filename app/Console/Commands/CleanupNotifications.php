<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cleanup
                           {--days=30 : Number of days after which to delete old notifications}
                           {--tokens : Also cleanup inactive FCM tokens}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old notifications and inactive FCM tokens';

    /**
     * Execute the console command.
     */
    public function handle(FirebaseService $firebaseService): int
    {
        $days = (int) $this->option('days');
        $cleanupTokens = $this->option('tokens');

        $this->info("Starting notification cleanup for items older than {$days} days...");

        try {
            // Clean up old read notifications
            $deletedNotifications = Notification::whereNotNull('read_at')
                ->where('read_at', '<', now()->subDays($days))
                ->delete();

            $this->info("Deleted {$deletedNotifications} old read notifications.");

            // Clean up very old unread notifications (double the time period)
            $veryOldDays = $days * 2;
            $deletedUnreadNotifications = Notification::whereNull('read_at')
                ->where('created_at', '<', now()->subDays($veryOldDays))
                ->delete();

            $this->info("Deleted {$deletedUnreadNotifications} very old unread notifications.");

            if ($cleanupTokens) {
                // Clean up inactive FCM tokens
                $deletedInactiveTokens = $firebaseService->cleanupInactiveTokens();
                $this->info("Deleted {$deletedInactiveTokens} inactive FCM tokens.");

                // Clean up unused FCM tokens
                $deletedUnusedTokens = $firebaseService->cleanupUnusedTokens();
                $this->info("Deleted {$deletedUnusedTokens} unused FCM tokens.");
            }

            $totalDeleted = $deletedNotifications + $deletedUnreadNotifications;
            if ($cleanupTokens) {
                $totalDeleted += $deletedInactiveTokens + $deletedUnusedTokens;
            }

            $this->info("✅ Cleanup completed successfully! Total items cleaned: {$totalDeleted}");
            Log::info("Notification cleanup completed", [
                'deleted_notifications' => $deletedNotifications + $deletedUnreadNotifications,
                'deleted_tokens' => $cleanupTokens ? $deletedInactiveTokens + $deletedUnusedTokens : 0,
                'days' => $days
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: " . $e->getMessage());
            Log::error("Notification cleanup failed: " . $e->getMessage());
            
            return 1;
        }
    }
}
