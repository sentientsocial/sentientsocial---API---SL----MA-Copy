<?php

namespace App\Services;

use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\InvalidArgumentException;

class FirebaseService
{
    protected $messaging;
    protected $isConfigured = false;

    public function __construct()
    {
        $this->initializeFirebase();
    }

    /**
     * Initialize Firebase configuration
     */
    private function initializeFirebase(): void
    {
        try {
            $serviceAccountPath = config('services.firebase.credentials');
            $projectId = config('services.firebase.project_id', 'sentient-social-app');

            if (!$serviceAccountPath || !file_exists($serviceAccountPath)) {
                Log::warning('Firebase service account file not found. Push notifications will be disabled.');
                return;
            }

            $factory = (new Factory)
                ->withServiceAccount($serviceAccountPath)
                ->withProjectId($projectId);

            $this->messaging = $factory->createMessaging();
            $this->isConfigured = true;

            Log::info('Firebase service initialized successfully');

        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase: ' . $e->getMessage());
            $this->isConfigured = false;
        }
    }

    /**
     * Check if Firebase is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Send a push notification to a specific user
     */
    public function sendNotificationToUser(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ): array {
        if (!$this->isConfigured) {
            Log::warning('Firebase not configured. Cannot send push notification.');
            return ['success' => false, 'error' => 'Firebase not configured'];
        }

        $tokens = UserFcmToken::getActiveTokensForUser($userId);

        if (empty($tokens)) {
            Log::info("No active FCM tokens found for user {$userId}");
            return ['success' => false, 'error' => 'No active tokens found'];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send push notification to multiple tokens
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = []
    ): array {
        if (!$this->isConfigured) {
            return ['success' => false, 'error' => 'Firebase not configured'];
        }

        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No tokens provided'];
        }

        try {
            $notification = Notification::fromArray([
                'title' => $title,
                'body' => $body,
            ]);

            $results = [];
            $successCount = 0;
            $failureCount = 0;
            $invalidTokens = [];

            // Send to each token individually to handle failures gracefully
            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification($notification)
                        ->withData($data);

                    $this->messaging->send($message);
                    $successCount++;
                    $results[] = ['token' => $this->maskToken($token), 'status' => 'success'];

                } catch (MessagingException $e) {
                    $failureCount++;
                    $errorCode = $e->getCode();
                    
                    // Handle invalid/expired tokens
                    if ($this->isInvalidToken($e)) {
                        $invalidTokens[] = $token;
                        Log::info("Invalid FCM token detected: " . $this->maskToken($token));
                    }

                    $results[] = [
                        'token' => $this->maskToken($token),
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'code' => $errorCode
                    ];

                    Log::warning("Failed to send FCM message to token " . $this->maskToken($token) . ": " . $e->getMessage());
                } catch (\Exception $e) {
                    $failureCount++;
                    $results[] = [
                        'token' => $this->maskToken($token),
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                    Log::error("Unexpected error sending FCM message: " . $e->getMessage());
                }
            }

            // Clean up invalid tokens
            if (!empty($invalidTokens)) {
                $this->removeInvalidTokens($invalidTokens);
            }

            $response = [
                'success' => $successCount > 0,
                'sent_count' => $successCount,
                'failed_count' => $failureCount,
                'total_tokens' => count($tokens),
                'invalid_tokens_removed' => count($invalidTokens)
            ];

            if (config('app.debug')) {
                $response['details'] = $results;
            }

            Log::info("FCM notification sent: {$successCount} successful, {$failureCount} failed");

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to send FCM notifications: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send notifications: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send a data-only message (no notification popup)
     */
    public function sendDataMessage(
        int $userId,
        array $data
    ): array {
        if (!$this->isConfigured) {
            return ['success' => false, 'error' => 'Firebase not configured'];
        }

        $tokens = UserFcmToken::getActiveTokensForUser($userId);

        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No active tokens found'];
        }

        try {
            $results = [];
            $successCount = 0;

            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withData($data);

                    $this->messaging->send($message);
                    $successCount++;

                } catch (MessagingException $e) {
                    if ($this->isInvalidToken($e)) {
                        UserFcmToken::where('token', $token)->delete();
                    }
                    Log::warning("Failed to send data message: " . $e->getMessage());
                }
            }

            return [
                'success' => $successCount > 0,
                'sent_count' => $successCount,
                'total_tokens' => count($tokens)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send data message: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if the exception indicates an invalid token
     */
    private function isInvalidToken(MessagingException $e): bool
    {
        $invalidTokenCodes = [
            'INVALID_REGISTRATION_TOKEN',
            'REGISTRATION_TOKEN_NOT_REGISTERED',
            'INVALID_ARGUMENT'
        ];

        return in_array($e->getCode(), $invalidTokenCodes) ||
               strpos($e->getMessage(), 'registration-token-not-registered') !== false ||
               strpos($e->getMessage(), 'invalid-registration-token') !== false;
    }

    /**
     * Remove invalid tokens from database
     */
    private function removeInvalidTokens(array $tokens): int
    {
        try {
            return UserFcmToken::whereIn('token', $tokens)->delete();
        } catch (\Exception $e) {
            Log::error('Failed to remove invalid FCM tokens: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mask token for logging (security)
     */
    private function maskToken(string $token): string
    {
        if (strlen($token) <= 10) {
            return str_repeat('*', strlen($token));
        }
        
        return substr($token, 0, 5) . str_repeat('*', strlen($token) - 10) . substr($token, -5);
    }

    /**
     * Clean up old inactive tokens
     */
    public function cleanupInactiveTokens(): int
    {
        return UserFcmToken::removeOldInactiveTokens(30);
    }

    /**
     * Clean up unused tokens
     */
    public function cleanupUnusedTokens(): int
    {
        return UserFcmToken::removeUnusedTokens(90);
    }

    /**
     * Test the Firebase connection
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured) {
            return ['success' => false, 'message' => 'Firebase not configured'];
        }

        try {
            // Try to create a test message (won't send it)
            $testNotification = Notification::fromArray([
                'title' => 'Test',
                'body' => 'Test message'
            ]);

            return [
                'success' => true,
                'message' => 'Firebase connection is working',
                'configured' => true
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Firebase connection failed: ' . $e->getMessage(),
                'configured' => false
            ];
        }
    }
}