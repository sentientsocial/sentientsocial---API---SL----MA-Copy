<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Notification;
use App\Models\UserFcmToken;
use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:push-notification 
                           {--user-id= : User ID to send notification to}
                           {--email= : User email to send notification to}
                           {--token= : Specific FCM token to send to}
                           {--title=Test Notification : Notification title}
                           {--message=This is a test push notification : Notification message}
                           {--type=test : Notification type}';

    /**
     * The console command description.
     */
    protected $description = 'Send a test push notification to verify Firebase integration';

    /**
     * Execute the console command.
     */
    public function handle(FirebaseService $firebaseService): int
    {
        $this->info('🚀 Starting Push Notification Test...');
        $this->newLine();

        // Test Firebase connection first
        $connectionTest = $firebaseService->testConnection();
        if (!$connectionTest['success']) {
            $this->error('❌ Firebase connection failed: ' . $connectionTest['message']);
            return 1;
        }
        $this->info('✅ Firebase connection successful');

        $userId = $this->option('user-id');
        $email = $this->option('email');
        $specificToken = $this->option('token');
        $title = $this->option('title');
        $message = $this->option('message');
        $type = $this->option('type');

        try {
            if ($specificToken) {
                // Test with specific FCM token
                $this->info("📱 Sending to specific token: " . substr($specificToken, 0, 20) . '...');
                $result = $firebaseService->sendToTokens([$specificToken], $title, $message, [
                    'type' => $type,
                    'test' => true,
                    'timestamp' => now()->toISOString()
                ]);
            } else {
                // Find user by ID or email
                $user = null;
                if ($userId) {
                    $user = User::find($userId);
                    if (!$user) {
                        $this->error("❌ User with ID {$userId} not found");
                        return 1;
                    }
                } elseif ($email) {
                    $user = User::where('email', $email)->first();
                    if (!$user) {
                        $this->error("❌ User with email {$email} not found");
                        return 1;
                    }
                } else {
                    // Get first user with FCM tokens for testing
                    $user = User::whereHas('fcmTokens', function ($query) {
                        $query->where('is_active', true);
                    })->first();

                    if (!$user) {
                        $this->error('❌ No users with active FCM tokens found');
                        $this->info('💡 Try registering an FCM token from your app first');
                        return 1;
                    }
                }

                $this->info("👤 Selected user: {$user->name} ({$user->email})");

                // Check if user has FCM tokens
                $tokenCount = $user->fcmTokens()->where('is_active', true)->count();
                if ($tokenCount === 0) {
                    $this->warn("⚠️ User has no active FCM tokens");
                    $this->info("💡 Make sure the user has opened the app and allowed notifications");
                    return 1;
                }

                $this->info("📱 Found {$tokenCount} active FCM token(s) for user");

                // Create a test notification in database
                $notification = Notification::createSystemNotification(
                    $user->id,
                    $title,
                    $message,
                    ['type' => $type, 'test' => true]
                );

                $this->info("💾 Created notification in database (ID: {$notification->id})");

                // Send push notification
                $result = $firebaseService->sendNotificationToUser(
                    $user->id,
                    $title,
                    $message,
                    [
                        'type' => $type,
                        'notification_id' => $notification->id,
                        'test' => true,
                        'timestamp' => now()->toISOString()
                    ]
                );
            }

            // Display results
            $this->newLine();
            if ($result['success']) {
                $this->info('✅ Push notification sent successfully!');
                $this->info("📊 Sent to {$result['sent_count']} device(s)");
                
                if ($result['failed_count'] > 0) {
                    $this->warn("⚠️ Failed to send to {$result['failed_count']} device(s)");
                }

                if (isset($result['invalid_tokens_removed']) && $result['invalid_tokens_removed'] > 0) {
                    $this->info("🧹 Removed {$result['invalid_tokens_removed']} invalid token(s)");
                }

            } else {
                $this->error('❌ Failed to send push notification');
                $this->error('Error: ' . ($result['error'] ?? 'Unknown error'));
                return 1;
            }

            $this->newLine();
            $this->info('🎉 Test completed! Check your mobile app for the notification.');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Test failed with error: ' . $e->getMessage());
            Log::error('Push notification test failed: ' . $e->getMessage());
            return 1;
        }
    }
}
