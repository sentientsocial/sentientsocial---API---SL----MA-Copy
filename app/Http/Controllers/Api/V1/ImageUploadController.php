<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadController extends Controller
{
    /**
     * Upload profile avatar image
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        try {
            $file = $request->file('avatar');
            $filename = 'avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Create image manager instance
            $manager = new ImageManager(new Driver());
            
            // Resize image to 400x400 for avatar
            $image = $manager->read($file->getPathname());
            $resizedImage = $image->resize(400, 400);
            
            // Store the resized image
            Storage::disk('public')->put($filename, $resizedImage->encode());
            
            // Store only the filename in database
            $user = $request->user();
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['avatar' => $filename] // Store only filename
            );

            return response()->json([
                'message' => 'Avatar uploaded successfully',
                'avatar_url' => $filename, // Return filename for Flutter to construct URL
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload background/cover image (no forced resize)
     */
    public function uploadBackground(Request $request)
    {
        $request->validate([
            'background' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('background');
            $filename = 'backgrounds/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Store the original image without forced resizing
            Storage::disk('public')->put($filename, file_get_contents($file->getPathname()));
            
            // Store only the filename in database
            $user = $request->user();
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['background_image' => $filename] // Store only filename
            );

            return response()->json([
                'message' => 'Background image uploaded successfully',
                'background_url' => $filename, // Return filename for Flutter to construct URL
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload background image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
