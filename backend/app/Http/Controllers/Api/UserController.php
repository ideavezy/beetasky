<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Update the authenticated user's profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'avatar_url' => 'nullable|url|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $data = $validator->validated();
        
        // Update only provided fields
        if (isset($data['first_name'])) {
            $user->first_name = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $user->last_name = $data['last_name'];
        }
        
        // Note: Email updates are handled by Supabase auth
        // We don't update email in our database directly
        // as it needs to be verified through Supabase
        
        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }
        
        if (isset($data['avatar_url'])) {
            $user->avatar_url = $data['avatar_url'];
        }
        
        $user->save();
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }
    
    /**
     * Get the authenticated user's profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        $user->load(['companies', 'activeCompanies']);
        
        // Get or create user presets
        $preset = $user->getOrCreatePreset();
        $preset->load('defaultCompany');
        
        return response()->json([
            'user' => $user,
            'presets' => [
                'default_company_id' => $preset->default_company_id,
                'default_company' => $preset->defaultCompany ? [
                    'id' => $preset->defaultCompany->id,
                    'name' => $preset->defaultCompany->name,
                    'slug' => $preset->defaultCompany->slug,
                    'logo_url' => $preset->defaultCompany->logo_url,
                ] : null,
                'settings' => $preset->settings,
            ],
        ]);
    }
}


