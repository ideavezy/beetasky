<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Bunny.net Storage Service
 * 
 * Reusable service for handling file uploads to Bunny.net CDN.
 * Works with any entity type across the application.
 */
class BunnyStorageService
{
    protected string $storageZone;
    protected string $storagePassword;
    protected string $storageUrl;
    protected string $region;
    protected string $baseUrl;
    protected int $maxFileSize;

    public function __construct()
    {
        $this->storageZone = config('services.bunny.storage_zone');
        $this->storagePassword = config('services.bunny.storage_password');
        $this->storageUrl = config('services.bunny.storage_url');
        $this->region = config('services.bunny.region');
        $this->baseUrl = config('services.bunny.base_url');
        $this->maxFileSize = config('services.bunny.max_file_size', 26214400);
    }

    /**
     * Generate a short-lived signed upload URL for direct browser-to-CDN upload
     * 
     * Note: Bunny.net Storage API requires the AccessKey header, not URL signing.
     * We return the URL and the access key separately so the client can add it as a header.
     * 
     * @param string $path The storage path (e.g., "company-id/tasks/task-id/comments/file.pdf")
     * @param array $options Additional options (ttl, content_type)
     * @return array Contains upload_url, access_key, and expires_at
     */
    public function generateUploadUrl(string $path, array $options = []): array
    {
        $ttl = $options['ttl'] ?? 60; // Default 60 seconds
        $expiresAt = now()->addSeconds($ttl);
        
        // Build the full upload URL - ensure no double slashes
        $baseUrl = rtrim($this->storageUrl, '/');
        $storageZone = trim($this->storageZone, '/');
        $filePath = ltrim($path, '/');
        $uploadUrl = "{$baseUrl}/{$storageZone}/{$filePath}";
        
        return [
            'upload_url' => $uploadUrl,
            'access_key' => $this->storagePassword,
            'expires_at' => $expiresAt->toIso8601String(),
            'path' => $path,
        ];
    }

    /**
     * Verify that a file exists on Bunny CDN
     * 
     * @param string $path The storage path
     * @return bool True if file exists
     */
    public function verifyFileExists(string $path): bool
    {
        try {
            $baseUrl = rtrim($this->storageUrl, '/');
            $storageZone = trim($this->storageZone, '/');
            $filePath = ltrim($path, '/');
            $url = "{$baseUrl}/{$storageZone}/{$filePath}";
            
            // Use GET with Range header to minimize data transfer
            $response = Http::withHeaders([
                'AccessKey' => $this->storagePassword,
                'Range' => 'bytes=0-0',
            ])->get($url);
            
            // Accept both 200 (full response) and 206 (partial content)
            return $response->successful() || $response->status() === 206;
        } catch (\Exception $e) {
            \Log::warning('File verification failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get public CDN URL for a file
     * 
     * @param string $path The storage path
     * @return string Public CDN URL
     */
    public function getPublicUrl(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Delete a file from Bunny storage
     * 
     * @param string $path The storage path
     * @return bool True if deletion was successful
     */
    public function deleteFile(string $path): bool
    {
        try {
            $baseUrl = rtrim($this->storageUrl, '/');
            $storageZone = trim($this->storageZone, '/');
            $filePath = ltrim($path, '/');
            $url = "{$baseUrl}/{$storageZone}/{$filePath}";
            
            $response = Http::withHeaders([
                'AccessKey' => $this->storagePassword,
            ])->delete($url);
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate a storage path for a file
     * 
     * @param string $companyId Company UUID
     * @param string $entityType Entity type (task_comment, user_avatar, project, etc.)
     * @param string $entityId Entity UUID (optional for new entities)
     * @param string $filename Original filename
     * @return string Generated storage path
     */
    public function generateStoragePath(
        string $companyId,
        string $entityType,
        ?string $entityId,
        string $filename
    ): string {
        // Generate unique filename to prevent collisions
        $uniqueFilename = $this->generateUniqueFilename($filename);
        
        // Build path based on entity type
        $pathParts = [$companyId];
        
        // Add entity-specific path segments
        switch ($entityType) {
            case 'task_comment':
                $pathParts[] = 'tasks';
                if ($entityId) {
                    $pathParts[] = $entityId;
                }
                $pathParts[] = 'comments';
                break;
                
            case 'user_avatar':
                $pathParts[] = 'users';
                if ($entityId) {
                    $pathParts[] = $entityId;
                }
                $pathParts[] = 'avatar';
                break;
                
            case 'project':
                $pathParts[] = 'projects';
                if ($entityId) {
                    $pathParts[] = $entityId;
                }
                $pathParts[] = 'files';
                break;
                
            case 'company_logo':
                $pathParts[] = 'company';
                $pathParts[] = 'logo';
                break;
                
            case 'contact':
                $pathParts[] = 'contacts';
                if ($entityId) {
                    $pathParts[] = $entityId;
                }
                break;
                
            default:
                // Generic path for other entity types
                $pathParts[] = Str::plural($entityType);
                if ($entityId) {
                    $pathParts[] = $entityId;
                }
                break;
        }
        
        // Add filename
        $pathParts[] = $uniqueFilename;
        
        return implode('/', $pathParts);
    }

    /**
     * Generate a unique filename to prevent collisions
     * 
     * @param string $originalFilename Original filename
     * @return string Unique filename with timestamp and random string
     */
    protected function generateUniqueFilename(string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = Str::slug($basename);
        
        // Generate unique prefix: timestamp + random string
        $uniquePrefix = time() . '_' . Str::random(8);
        
        return $extension 
            ? "{$uniquePrefix}_{$basename}.{$extension}"
            : "{$uniquePrefix}_{$basename}";
    }

    /**
     * Validate file size
     * 
     * @param int $size File size in bytes
     * @return bool True if valid
     */
    public function validateFileSize(int $size): bool
    {
        return $size > 0 && $size <= $this->maxFileSize;
    }

    /**
     * Validate MIME type against whitelist
     * 
     * @param string $mimeType MIME type to validate
     * @return bool True if valid
     */
    public function validateMimeType(string $mimeType): bool
    {
        $allowedTypes = [
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            
            // Archives
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
        ];
        
        return in_array($mimeType, $allowedTypes);
    }

    /**
     * Get max file size in bytes
     * 
     * @return int Max file size
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * Get max file size in human-readable format
     * 
     * @return string Formatted size (e.g., "25 MB")
     */
    public function getMaxFileSizeFormatted(): string
    {
        return round($this->maxFileSize / 1048576, 0) . ' MB';
    }
}

