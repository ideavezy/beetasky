<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authenticate MCP requests.
 * 
 * Supports two authentication methods:
 * 1. Supabase JWT - via Authorization: Bearer <jwt>
 * 2. API Key - via Authorization: Bearer <api_key> or X-API-Key header
 * 
 * API keys are identified by the 'bsk_' prefix.
 */
class McpAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try to get token from Authorization header
        $authHeader = $request->header('Authorization');
        $apiKeyHeader = $request->header('X-API-Key');

        // Check for API key first (in X-API-Key header or Bearer token with bsk_ prefix)
        $apiKey = null;
        if ($apiKeyHeader) {
            $apiKey = $apiKeyHeader;
        } elseif ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if (str_starts_with($token, 'bsk_')) {
                $apiKey = $token;
            }
        }

        // Authenticate with API key if present
        if ($apiKey) {
            return $this->authenticateWithApiKey($request, $next, $apiKey);
        }

        // Fall back to Supabase JWT authentication
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return $this->authenticateWithJwt($request, $next, $token);
        }

        return response()->json(['message' => 'Unauthorized - No authentication provided'], 401);
    }

    /**
     * Authenticate using an API key.
     */
    protected function authenticateWithApiKey(Request $request, Closure $next, string $apiKey): Response
    {
        $validatedKey = ApiKey::validate($apiKey);

        if (!$validatedKey) {
            return response()->json(['message' => 'Invalid or expired API key'], 401);
        }

        // Get the user associated with the API key
        $user = $validatedKey->user;

        if (!$user) {
            return response()->json(['message' => 'API key user not found'], 401);
        }

        // Set the authenticated user
        Auth::setUser($user);

        // Store API key info in request for later use (scoping, etc.)
        $request->attributes->set('api_key', $validatedKey);
        $request->attributes->set('auth_method', 'api_key');

        // If the API key has a company_id, set it as the X-Company-ID header if not already set
        if ($validatedKey->company_id && !$request->header('X-Company-ID')) {
            $request->headers->set('X-Company-ID', $validatedKey->company_id);
        }

        return $next($request);
    }

    /**
     * Authenticate using Supabase JWT.
     */
    protected function authenticateWithJwt(Request $request, Closure $next, string $token): Response
    {
        try {
            // Decode and validate the JWT
            $jwtSecret = config('supabase.jwt_secret');

            if (!$jwtSecret) {
                throw new \Exception('Supabase JWT secret not configured');
            }

            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));

            // Extract user info from the token
            $supabaseUserId = $decoded->sub ?? null;
            $email = $decoded->email ?? null;

            if (!$supabaseUserId) {
                return response()->json(['message' => 'Invalid token - no user ID'], 401);
            }

            // Find or create the user in our database
            $user = User::where('id', $supabaseUserId)->first();

            if (!$user && $email) {
                // Try to find by email and update their ID to match Supabase
                $user = User::where('email', $email)->first();

                if ($user) {
                    $user->id = $supabaseUserId;
                    $user->save();
                }
            }

            if (!$user) {
                // Create new user from Supabase data
                $userMetadata = $decoded->user_metadata ?? (object)[];

                $user = User::create([
                    'id' => $supabaseUserId,
                    'email' => $email,
                    'first_name' => $userMetadata->first_name ?? $userMetadata->name ?? 'User',
                    'last_name' => $userMetadata->last_name ?? '',
                    'avatar_url' => $userMetadata->avatar_url ?? $userMetadata->picture ?? null,
                    'global_role' => 'user',
                    'email_verified_at' => isset($decoded->email_confirmed_at) ? now() : null,
                ]);
            }

            // Set the authenticated user
            Auth::setUser($user);
            $request->attributes->set('auth_method', 'jwt');

            return $next($request);

        } catch (\Firebase\JWT\ExpiredException $e) {
            return response()->json(['message' => 'Token expired'], 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return response()->json(['message' => 'Invalid token signature'], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 401);
        }
    }
}

