<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MeditationSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MeditationSessionController extends Controller
{
    /**
     * Display a listing of the user's meditation sessions.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $sessions = MeditationSession::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $sessions->items(),
                'meta' => [
                    'current_page' => $sessions->currentPage(),
                    'from' => $sessions->firstItem(),
                    'last_page' => $sessions->lastPage(),
                    'per_page' => $sessions->perPage(),
                    'to' => $sessions->lastItem(),
                    'total' => $sessions->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching meditation sessions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching meditation sessions'
            ], 500);
        }
    }

    /**
     * Store a newly created meditation session.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'duration' => 'required|integer|min:1|max:120',
                'meditation_type' => 'required|string|in:mindfulness,guided,breathing,body_scan,loving_kindness,visualization',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $session = MeditationSession::create([
                'user_id' => Auth::id(),
                'duration' => $request->duration,
                'meditation_type' => $request->meditation_type,
                'notes' => $request->notes,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Meditation session created successfully',
                'data' => $session
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating meditation session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating meditation session'
            ], 500);
        }
    }

    /**
     * Display the specified meditation session.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $session = MeditationSession::where('user_id', Auth::id())
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $session
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Meditation session not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching meditation session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching meditation session'
            ], 500);
        }
    }

    /**
     * Update the specified meditation session.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $session = MeditationSession::where('user_id', Auth::id())
                ->findOrFail($id);

            // Only allow updates to sessions that are not completed
            if ($session->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update completed meditation session'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'duration' => 'sometimes|integer|min:1|max:120',
                'meditation_type' => 'sometimes|string|in:mindfulness,guided,breathing,body_scan,loving_kindness,visualization',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $session->update($request->only([
                'duration', 'meditation_type', 'notes'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Meditation session updated successfully',
                'data' => $session->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Meditation session not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating meditation session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating meditation session'
            ], 500);
        }
    }

    /**
     * Remove the specified meditation session.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $session = MeditationSession::where('user_id', Auth::id())
                ->findOrFail($id);

            // Only allow deletion of sessions that are not completed
            if ($session->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete completed meditation session'
                ], 403);
            }

            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'Meditation session deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Meditation session not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting meditation session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting meditation session'
            ], 500);
        }
    }

    /**
     * Complete a meditation session.
     */
    /**
     * Complete a meditation session
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        try {
            $session = MeditationSession::where('user_id', Auth::id())
                ->findOrFail($id);

            // Check if session is already completed
            if ($session->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Meditation session is already completed'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'actual_duration' => 'required|integer|min:1|max:120',
                'notes' => 'nullable|string|max:1000',
                'mood_after' => 'nullable|string|in:peaceful,calm,energized,relaxed,focused,grateful,happy,content,refreshed,centered',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $session->update([
                'actual_duration' => $request->actual_duration,
                'notes' => $request->notes,
                'mood_after' => $request->mood_after,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update user's meditation statistics if profile exists
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if ($user->profile) {
                $user->profile->increment('meditation_minutes', $request->actual_duration);
                $user->profile->last_meditation_at = now();
                $user->profile->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Meditation session completed successfully',
                'data' => $session->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Meditation session not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error completing meditation session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error completing meditation session'
            ], 500);
        }
    }

    /**
     * Get user's meditation statistics.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        try {
            /** @var int $userId */
            $userId = Auth::id();
            
            if (!$userId) {
                Log::warning('Meditation statistics requested without authenticated user');
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            Log::info("Fetching meditation statistics for user: {$userId}");

            // Build base query
            $sessionsQuery = MeditationSession::where('user_id', $userId)
                ->where('status', 'completed');

            // Get basic statistics
            $totalSessions = $sessionsQuery->count();
            $totalMinutes = $sessionsQuery->sum('actual_duration') ?? 0;
            $averageSession = $totalSessions > 0 ? round($totalMinutes / $totalSessions, 1) : 0;

            Log::info("Basic stats - Sessions: {$totalSessions}, Minutes: {$totalMinutes}");

            // Calculate streak (consecutive days with meditation)
            $streak = 0;
            try {
                $streak = $this->calculateMeditationStreak($userId);
                Log::info("Calculated streak: {$streak}");
            } catch (\Exception $e) {
                Log::error('Error calculating meditation streak: ' . $e->getMessage());
                // Continue with streak = 0
            }

            // Get sessions by type
            $sessionsByType = [];
            try {
                $allSessions = $sessionsQuery->get();
                $sessionsByType = $allSessions
                    ->groupBy('meditation_type')
                    ->map(function ($sessions) {
                        return [
                            'count' => $sessions->count(),
                            'total_minutes' => $sessions->sum('actual_duration')
                        ];
                    })
                    ->toArray();
                Log::info("Sessions by type calculated: " . json_encode(array_keys($sessionsByType)));
            } catch (\Exception $e) {
                Log::error('Error calculating sessions by type: ' . $e->getMessage());
                // Continue with empty array
            }

            // Get last meditation
            $lastMeditation = null;
            try {
                $lastSession = MeditationSession::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->latest('completed_at')
                    ->first();
                $lastMeditation = $lastSession?->completed_at;
                Log::info("Last meditation: " . ($lastMeditation ? $lastMeditation->toISOString() : 'null'));
            } catch (\Exception $e) {
                Log::error('Error fetching last meditation: ' . $e->getMessage());
                // Continue with null
            }

            $responseData = [
                'total_sessions' => $totalSessions,
                'total_minutes' => $totalMinutes,
                'average_session_minutes' => $averageSession,
                'current_streak' => $streak,
                'sessions_by_type' => $sessionsByType,
                'last_meditation' => $lastMeditation,
            ];

            Log::info("Meditation statistics response prepared successfully");

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching meditation statistics: ' . $e->getMessage() . ' | Stack: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching meditation statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate current meditation streak.
     */
    private function calculateMeditationStreak(int $userId): int
    {
        try {
            Log::info("Calculating meditation streak for user: {$userId}");
            
            $sessions = MeditationSession::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereNotNull('completed_at')
                ->selectRaw('DATE(completed_at) as date')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->pluck('date')
                ->toArray();

            Log::info("Found meditation dates: " . implode(', ', array_slice($sessions, 0, 10)));

            if (empty($sessions)) {
                Log::info("No completed sessions found, streak = 0");
                return 0;
            }

            $streak = 0;
            $currentDate = now()->format('Y-m-d');
            $yesterday = now()->subDay()->format('Y-m-d');

            Log::info("Current date: {$currentDate}, Yesterday: {$yesterday}");

            // Check if there's a session today or yesterday
            if (!in_array($currentDate, $sessions) && !in_array($yesterday, $sessions)) {
                Log::info("No session today or yesterday, streak = 0");
                return 0;
            }

            // Start from today or yesterday
            $checkDate = in_array($currentDate, $sessions) ? $currentDate : $yesterday;
            Log::info("Starting streak calculation from: {$checkDate}");

            foreach ($sessions as $sessionDate) {
                if ($sessionDate === $checkDate) {
                    $streak++;
                    $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
                    Log::debug("Streak increment: {$streak}, next check date: {$checkDate}");
                } else {
                    break;
                }
            }

            Log::info("Final calculated streak: {$streak}");
            return $streak;
            
        } catch (\Exception $e) {
            Log::error('Error in calculateMeditationStreak: ' . $e->getMessage());
            return 0;
        }
    }
}
