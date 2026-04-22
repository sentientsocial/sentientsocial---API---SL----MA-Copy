<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserDailyHealthMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HealthController extends Controller
{
    /**
     * Sync health data from Terra Health API
     */
    public function sync(Request $request)
    {
        // Validate the request structure
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.metadata.start_time' => 'required|string',
            'data.*.metadata.end_time' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data format',
                'errors' => $validator->errors()
            ], 422);
        }

        $syncedCount = 0;
        $errors = [];

        try {
            foreach ($request->input('data') as $index => $dayData) {
                try {
                    // Parse the date from start_time
                    $date = Carbon::parse($dayData['metadata']['start_time'])->toDateString();
                    
                    // Extract health metrics with null coalescing for safety
                    $healthData = [
                        'user_id' => $request->user()->id,
                        'date' => $date,
                        'terra_user_id' => $dayData['terra_user_id'] ?? null,
                        'steps' => $dayData['distance_data']['steps'] ?? 0,
                        'distance_meters' => $dayData['distance_data']['distance_meters'] ?? 0,
                        'total_calories' => $dayData['calories_data']['total_burned_calories'] ?? 0,
                        'active_calories' => $dayData['calories_data']['net_activity_calories'] ?? 0,
                        'resting_heart_rate' => $dayData['heart_rate_data']['summary']['resting_hr_bpm'] ?? null,
                        'activity_seconds' => $dayData['active_durations_data']['activity_seconds'] ?? 0,
                        'source' => $dayData['device_data']['manufacturer'] ?? 'Unknown',
                        'raw_data' => $dayData, // Store full JSON for future reference
                    ];

                    // Use updateOrCreate to handle duplicates
                    UserDailyHealthMetric::updateOrCreate(
                        [
                            'user_id' => $healthData['user_id'],
                            'date' => $healthData['date']
                        ],
                        $healthData
                    );

                    $syncedCount++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$syncedCount} health records",
                'data' => [
                    'synced_count' => $syncedCount,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync health data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's health metrics for a date range
     */
    public function getMetrics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date range',
                'errors' => $validator->errors()
            ], 422);
        }

        $metrics = UserDailyHealthMetric::where('user_id', $request->user()->id)
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }
}
