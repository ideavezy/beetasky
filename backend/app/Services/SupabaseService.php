<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Supabase Service
 * 
 * This service handles communication with Supabase for features
 * that require direct API access (e.g., Storage, Realtime, Auth sync).
 * 
 * Note: For database operations, Laravel uses the PostgreSQL connection
 * configured in database.php (pointing to Supabase's PostgreSQL).
 */
class SupabaseService
{
    protected string $url;
    protected string $key;
    protected string $serviceRoleKey;

    public function __construct()
    {
        $this->url = config('supabase.url');
        $this->key = config('supabase.key');
        $this->serviceRoleKey = config('supabase.service_role_key');
    }

    /**
     * Get HTTP client configured for Supabase API
     */
    protected function client(bool $useServiceRole = false): \Illuminate\Http\Client\PendingRequest
    {
        $key = $useServiceRole ? $this->serviceRoleKey : $this->key;
        
        return Http::withHeaders([
            'apikey' => $key,
            'Authorization' => "Bearer {$key}",
            'Content-Type' => 'application/json',
        ])->baseUrl($this->url);
    }

    /**
     * Generate a JWT token for a user to use with Supabase Realtime
     * This allows frontend to connect to Supabase Realtime with user context
     */
    public function generateRealtimeToken(string $userId, array $claims = []): string
    {
        // TODO: Implement JWT generation for Supabase Realtime
        // This will be implemented when setting up live collaboration
        return '';
    }

    /**
     * Broadcast a realtime event to a channel
     */
    public function broadcast(string $channel, string $event, array $payload): bool
    {
        // TODO: Implement when setting up live collaboration
        return true;
    }

    /**
     * Get the Supabase URL for frontend configuration
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the anon key for frontend configuration
     */
    public function getAnonKey(): string
    {
        return $this->key;
    }
}

