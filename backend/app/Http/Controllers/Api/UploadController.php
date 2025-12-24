<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BunnyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Generic Upload Controller
 * 
 * Handles file upload preparation and confirmation for ANY entity type
 * across the application (task comments, user avatars, project files, etc.)
 */
class UploadController extends Controller
{
    protected BunnyStorageService $bunnyStorage;

    public function __construct(BunnyStorageService $bunnyStorage)
    {
        $this->bunnyStorage = $bunnyStorage;
    }

    /**
     * Prepare upload - Generate short-lived signed URL
     * 
     * POST /api/v1/upload/prepare
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function prepare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string|max:255',
            'mime_type' => 'required|string',
            'size' => 'required|integer|min:1',
            'entity_type' => 'required|string',
            'entity_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filename = $request->input('filename');
        $mimeType = $request->input('mime_type');
        $size = $request->input('size');
        $entityType = $request->input('entity_type');
        $entityId = $request->input('entity_id');

        // Validate file size
        if (!$this->bunnyStorage->validateFileSize($size)) {
            return response()->json([
                'success' => false,
                'message' => 'File size exceeds maximum allowed size of ' . $this->bunnyStorage->getMaxFileSizeFormatted(),
            ], 422);
        }

        // Validate MIME type
        if (!$this->bunnyStorage->validateMimeType($mimeType)) {
            return response()->json([
                'success' => false,
                'message' => 'File type not allowed. Please upload images, documents, or archives only.',
            ], 422);
        }

        // Validate user permissions for entity type
        $permissionCheck = $this->validateEntityPermission($request, $entityType, $entityId);
        if (!$permissionCheck['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $permissionCheck['message'],
            ], 403);
        }

        // Get company ID from authenticated user
        $companyId = $request->header('X-Company-ID');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company context is required',
            ], 400);
        }

        // Generate storage path
        $path = $this->bunnyStorage->generateStoragePath(
            $companyId,
            $entityType,
            $entityId,
            $filename
        );

        // Generate signed upload URL
        $uploadData = $this->bunnyStorage->generateUploadUrl($path, [
            'ttl' => 60, // 60 seconds
        ]);

        return response()->json([
            'success' => true,
            'upload_url' => $uploadData['upload_url'],
            'access_key' => $uploadData['access_key'],
            'path' => $uploadData['path'],
            'expires_at' => $uploadData['expires_at'],
        ]);
    }

    /**
     * Confirm upload - Verify file exists and return metadata
     * 
     * POST /api/v1/upload/confirm
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'filename' => 'required|string',
            'mime_type' => 'required|string',
            'size' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $request->input('path');
        $filename = $request->input('filename');
        $mimeType = $request->input('mime_type');
        $size = $request->input('size');

        // Verify file exists on Bunny CDN (optional - upload success implies existence)
        $verified = $this->bunnyStorage->verifyFileExists($path);

        // Log verification result but don't fail if it can't verify
        if (!$verified) {
            \Log::warning('File verification returned false, but proceeding anyway', [
                'path' => $path,
                'filename' => $filename,
            ]);
        }

        // Generate public URL
        $publicUrl = $this->bunnyStorage->getPublicUrl($path);

        return response()->json([
            'success' => true,
            'verified' => $verified,
            'path' => $path,
            'public_url' => $publicUrl,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
        ]);
    }

    /**
     * Validate user permissions for entity type
     * 
     * @param Request $request
     * @param string $entityType
     * @param string|null $entityId
     * @return array ['allowed' => bool, 'message' => string]
     */
    protected function validateEntityPermission(Request $request, string $entityType, ?string $entityId): array
    {
        $user = $request->user();

        // For now, basic authentication is sufficient
        // Additional permission checks can be added per entity type
        
        switch ($entityType) {
            case 'task_comment':
                // User must be authenticated and have access to a company
                if (!$user) {
                    return ['allowed' => false, 'message' => 'Authentication required'];
                }
                // TODO: Add specific task access check if entityId is provided
                return ['allowed' => true, 'message' => ''];

            case 'user_avatar':
                // User can only upload their own avatar
                if ($entityId && $entityId !== $user->id) {
                    return ['allowed' => false, 'message' => 'You can only upload your own avatar'];
                }
                return ['allowed' => true, 'message' => ''];

            case 'project':
                // User must be project member
                // TODO: Add project member check
                return ['allowed' => true, 'message' => ''];

            case 'company_logo':
                // User must be company owner or admin
                // TODO: Add role check
                return ['allowed' => true, 'message' => ''];

            case 'contact':
            case 'deal':
            case 'message':
            case 'knowledge_doc':
                // Basic authentication check
                return ['allowed' => true, 'message' => ''];

            default:
                // Allow by default for authenticated users
                return ['allowed' => true, 'message' => ''];
        }
    }
}

