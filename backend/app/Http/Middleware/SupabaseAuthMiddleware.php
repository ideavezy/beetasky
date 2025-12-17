<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authenticate requests using Supabase JWT tokens.
 * 
 * This middleware:
 * 1. Extracts the JWT from the Authorization header
 * 2. Validates the JWT using Supabase's JWT secret
 * 3. Finds or creates the corresponding Laravel user
 * 4. Sets the authenticated user for the request
 */
class SupabaseAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get the Authorization header
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized - No token provided'], 401);
        }
        
        $token = substr($authHeader, 7); // Remove "Bearer " prefix
        
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
                    // Update the user ID to match Supabase ID
                    // This handles the case where user was created locally first
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
                    'global_role' => 'user', // Default role for new users
                    'email_verified_at' => isset($decoded->email_confirmed_at) ? now() : null,
                ]);
            }
            
            // Set the authenticated user
            Auth::setUser($user);
            
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



