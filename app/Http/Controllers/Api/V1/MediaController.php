<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $media = Media::latest()->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $media->items(),
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'total' => $media->total()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching media: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching media'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:20480', // 20MB max
                'type' => 'nullable|string|in:image,video',
                'mediable_type' => 'nullable|string',
                'mediable_id' => 'nullable|integer',
            ]);

            $file = $request->file('file');
            
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid file upload'
                ], 400);
            }

            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();
            
            // Determine type if not provided
            $type = $request->type ?? $this->determineMediaType($mimeType);
            
            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $extension;
            
            // Store file in public storage
            $path = $file->storeAs('media/' . $type . 's', $filename, 'public');
            $url = Storage::url($path);
            
            // Generate thumbnail for images
            $thumbnailUrl = null;
            if ($type === 'image') {
                $thumbnailUrl = $this->generateThumbnail($path, $filename);
            }
            
            // Create media record - handle nullable mediable fields
            $media = Media::create([
                'mediable_type' => $request->mediable_type ?: null,
                'mediable_id' => $request->mediable_id ?: null,
                'type' => $type,
                'url' => $url,
                'thumbnail_url' => $thumbnailUrl,
                'metadata' => [
                    'original_name' => $originalName,
                    'size' => $file->getSize(),
                    'mime_type' => $mimeType,
                    'extension' => $extension
                ]
            ]);

            Log::info('Media uploaded successfully', ['media_id' => $media->id, 'filename' => $filename]);

            return response()->json([
                'success' => true,
                'message' => 'Media uploaded successfully',
                'data' => $media
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Media upload validation error: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false, 
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading media: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error uploading media: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload multiple media files
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'files' => 'required|array|max:10',
                'files.*' => 'file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:20480',
                'mediable_type' => 'nullable|string',
                'mediable_id' => 'nullable|integer',
            ]);

            $uploadedMedia = [];
            $errors = [];

            foreach ($request->file('files') as $index => $file) {
                try {
                    $originalName = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $mimeType = $file->getMimeType();
                    
                    $type = $this->determineMediaType($mimeType);
                    $filename = time() . '_' . Str::random(10) . '.' . $extension;
                    
                    $path = $file->storeAs('media/' . $type . 's', $filename, 'public');
                    $url = Storage::url($path);
                    
                    $thumbnailUrl = null;
                    if ($type === 'image') {
                        $thumbnailUrl = $this->generateThumbnail($path, $filename);
                    }
                    
                    $media = Media::create([
                        'mediable_type' => $request->mediable_type,
                        'mediable_id' => $request->mediable_id,
                        'type' => $type,
                        'url' => $url,
                        'thumbnail_url' => $thumbnailUrl,
                        'metadata' => [
                            'original_name' => $originalName,
                            'size' => $file->getSize(),
                            'mime_type' => $mimeType,
                            'extension' => $extension
                        ]
                    ]);

                    $uploadedMedia[] = $media;

                } catch (\Exception $e) {
                    $errors[] = "File $index: " . $e->getMessage();
                    Log::error("Error uploading file $index: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => count($uploadedMedia) > 0,
                'message' => count($uploadedMedia) . ' files uploaded successfully' . (count($errors) > 0 ? ', ' . count($errors) . ' failed' : ''),
                'data' => $uploadedMedia,
                'errors' => $errors
            ], count($uploadedMedia) > 0 ? 201 : 500);

        } catch (\Exception $e) {
            Log::error('Error uploading multiple media: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error uploading media files'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Media $media): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $media
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching media: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Media not found'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Media $media): JsonResponse
    {
        try {
            $request->validate([
                'mediable_type' => 'nullable|string',
                'mediable_id' => 'nullable|integer',
                'metadata' => 'nullable|array'
            ]);

            $media->update($request->only(['mediable_type', 'mediable_id', 'metadata']));

            return response()->json([
                'success' => true,
                'message' => 'Media updated successfully',
                'data' => $media
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating media: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error updating media'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Media $media): JsonResponse
    {
        try {
            // Delete files from storage
            if ($media->url) {
                $path = str_replace('/storage/', '', $media->url);
                Storage::disk('public')->delete($path);
            }
            
            if ($media->thumbnail_url) {
                $thumbnailPath = str_replace('/storage/', '', $media->thumbnail_url);
                Storage::disk('public')->delete($thumbnailPath);
            }

            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting media: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error deleting media'], 500);
        }
    }

    /**
     * Determine media type from MIME type
     */
    private function determineMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        
        return 'file';
    }

    /**
     * Generate thumbnail for image
     */
    private function generateThumbnail(string $originalPath, string $filename): ?string
    {
        try {
            $manager = new ImageManager(new Driver());
            $fullPath = Storage::disk('public')->path($originalPath);
            
            // Create thumbnail
            $thumbnail = $manager->read($fullPath);
            $thumbnail->resize(300, 300, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            
            // Save thumbnail
            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = 'media/thumbnails/' . $thumbnailFilename;
            $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);
            
            // Ensure thumbnail directory exists
            Storage::disk('public')->makeDirectory('media/thumbnails');
            
            $thumbnail->save($thumbnailFullPath);
            
            return Storage::url($thumbnailPath);
            
        } catch (\Exception $e) {
            Log::warning('Failed to generate thumbnail: ' . $e->getMessage());
            return null;
        }
    }
}
