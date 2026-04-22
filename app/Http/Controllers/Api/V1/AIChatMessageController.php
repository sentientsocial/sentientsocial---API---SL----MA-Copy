<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AIChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AIChatMessageController extends Controller
{
    /**
     * Get AI chat history for the authenticated user
     */
    public function index(Request $request)
    {
        $chatHistory = AIChatMessage::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $chatHistory->items(),
            'pagination' => [
                'current_page' => $chatHistory->currentPage(),
                'last_page' => $chatHistory->lastPage(),
                'per_page' => $chatHistory->perPage(),
                'total' => $chatHistory->total(),
            ]
        ]);
    }

    /**
     * Send a message to AI coach and store the conversation
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid message format',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate AI response (placeholder - replace with actual AI integration)
            $aiResponse = $this->generateAIResponse($request->message);

            // Save the conversation to database
            $chatMessage = AIChatMessage::create([
                'user_id' => $request->user()->id,
                'message' => $request->message,
                'response' => $aiResponse,
                'metadata' => [
                    'timestamp' => now()->toISOString(),
                    'model' => 'meditation-coach-v1',
                    'user_context' => $this->getUserContext($request->user())
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $chatMessage->id,
                    'message' => $chatMessage->message,
                    'response' => $chatMessage->response,
                    'created_at' => $chatMessage->created_at->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process AI chat message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate AI response based on user message
     * TODO: Replace with actual AI service integration (OpenAI, etc.)
     */
    private function generateAIResponse(string $message): string
    {
        $lowerMessage = strtolower($message);
        
        // Simple rule-based responses for demonstration
        // Replace this with actual AI service call
        if (strpos($lowerMessage, 'meditation') !== false || strpos($lowerMessage, 'meditate') !== false) {
            return "Meditation is a wonderful practice! Here are some tips to improve your meditation:\n\n1. Start with just 5-10 minutes daily\n2. Find a quiet, comfortable space\n3. Focus on your breath\n4. Don't worry about 'perfect' meditation - it's about practice\n5. Try guided meditations if you're starting out\n\nWhat specific aspect of meditation would you like to explore?";
        }
        
        if (strpos($lowerMessage, 'stress') !== false || strpos($lowerMessage, 'anxious') !== false) {
            return "I understand you're dealing with stress. Here are some immediate techniques that can help:\n\n1. **Deep Breathing**: Try the 4-7-8 technique (inhale 4, hold 7, exhale 8)\n2. **Progressive Muscle Relaxation**: Tense and release each muscle group\n3. **Mindfulness**: Focus on the present moment without judgment\n4. **Movement**: Even a short walk can help reduce stress\n\nWould you like me to guide you through any of these techniques?";
        }
        
        if (strpos($lowerMessage, 'sleep') !== false || strpos($lowerMessage, 'insomnia') !== false) {
            return "Good sleep is essential for wellbeing. Here are some tips for better sleep:\n\n1. **Sleep Schedule**: Go to bed and wake up at the same time daily\n2. **Wind Down**: Create a relaxing bedtime routine\n3. **Environment**: Keep your room cool, dark, and quiet\n4. **Limit Screens**: Avoid devices 1 hour before bed\n5. **Meditation**: Try a body scan or breathing meditation\n\nWould you like me to suggest a specific bedtime meditation routine?";
        }
        
        if (strpos($lowerMessage, 'motivation') !== false || strpos($lowerMessage, 'motivation') !== false) {
            return "Staying motivated on your wellness journey is important! Here's what can help:\n\n1. **Start Small**: Set achievable daily goals\n2. **Track Progress**: Use our app to see your meditation streak\n3. **Be Kind**: Self-compassion is more motivating than self-criticism\n4. **Community**: Connect with others on similar journeys\n5. **Celebrate Wins**: Acknowledge every step forward\n\nWhat's one small step you can take today toward your wellness goals?";
        }
        
        // Default response
        return "Thank you for sharing with me! As your AI wellness coach, I'm here to support your meditation and mindfulness journey. I can help with:\n\n• Meditation techniques and guidance\n• Stress management strategies\n• Sleep improvement tips\n• Motivation and goal setting\n• Mindfulness practices\n\nWhat specific area would you like to explore today?";
    }

    /**
     * Get user context for AI personalization
     */
    private function getUserContext($user): array
    {
        return [
            'meditation_sessions_count' => $user->meditationSessions()->count(),
            'total_meditation_minutes' => $user->profile->meditation_minutes ?? 0,
            'streak_count' => $user->profile->streak_count ?? 0,
            'meditation_goals' => $user->profile->meditation_goals ?? [],
            'last_session' => $user->meditationSessions()->latest()->first()?->created_at?->diffForHumans(),
        ];
    }
}
