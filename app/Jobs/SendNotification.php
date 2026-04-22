<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\FirebaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    private int $userId;
    private string $title;
    private string $message;
    private array $data;
    private ?int $notificationId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $userId,
        string $title,
        string $message,
        array $data = [],
        ?int $notificationId = null
    ) {
        $this->userId = $userId;
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->notificationId = $notificationId;
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseService $firebaseService): void
    {
        try {
            Log::info("Sending notification to user {$this->userId}: {$this->title}");

            // Send the push notification
            $result = $firebaseService->sendNotificationToUser(
                $this->userId,
                $this->title,
                $this->message,
                $this->data
            );

            if ($result['success']) {
                Log::info("Successfully sent notification to user {$this->userId}");
            } else {
                Log::warning("Failed to send notification to user {$this->userId}: " . ($result['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            Log::error("Error sending notification to user {$this->userId}: " . $e->getMessage());
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to send notification to user {$this->userId} after {$this->tries} attempts: " . $exception->getMessage());
        
        // Optionally, you could mark the notification as failed in the database
        if ($this->notificationId) {
            try {
                $notification = Notification::find($this->notificationId);
                if ($notification) {
                    $notification->update([
                        'data' => array_merge($notification->data ?? [], [
                            'push_failed' => true,
                            'push_error' => $exception->getMessage(),
                            'failed_at' => now()->toISOString()
                        ])
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to update notification failure status: " . $e->getMessage());
            }
        }
    }
}
