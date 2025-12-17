<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApiKeyController extends Controller
{
    /**
     * List all API keys for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        $query = ApiKey::forUser($user->id);

        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        }

        $keys = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($key) => [
                'id' => $key->id,
                'name' => $key->name,
                'key_prefix' => $key->key_prefix,
                'company_id' => $key->company_id,
                'scopes' => $key->scopes,
                'is_active' => $key->is_active,
                'last_used_at' => $key->last_used_at?->toIso8601String(),
                'expires_at' => $key->expires_at?->toIso8601String(),
                'created_at' => $key->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $keys,
        ]);
    }

    /**
     * Create a new API key.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'company_id' => 'nullable|uuid|exists:companies,id',
                'scopes' => 'nullable|array',
                'scopes.*' => 'string',
                'expires_in_days' => 'nullable|integer|min:1|max:365',
            ]);

            // If company_id is provided, verify user has access
            if (!empty($validated['company_id'])) {
                $hasAccess = $user->companies()->where('companies.id', $validated['company_id'])->exists();
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this company',
                    ], 403);
                }
            }

            // Calculate expiration date
            $expiresAt = null;
            if (!empty($validated['expires_in_days'])) {
                $expiresAt = now()->addDays($validated['expires_in_days']);
            }

            // Generate the API key
            $result = ApiKey::generate(
                userId: $user->id,
                name: $validated['name'],
                companyId: $validated['company_id'] ?? null,
                scopes: $validated['scopes'] ?? ['mcp:*'],
                expiresAt: $expiresAt
            );

            return response()->json([
                'success' => true,
                'message' => 'API key created successfully. Save this key - it will not be shown again.',
                'data' => [
                    'id' => $result['api_key']->id,
                    'name' => $result['api_key']->name,
                    'key' => $result['plain_key'], // Only returned once!
                    'key_prefix' => $result['api_key']->key_prefix,
                    'company_id' => $result['api_key']->company_id,
                    'scopes' => $result['api_key']->scopes,
                    'expires_at' => $result['api_key']->expires_at?->toIso8601String(),
                    'created_at' => $result['api_key']->created_at?->toIso8601String(),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create API key',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get details of a specific API key.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $key = ApiKey::forUser($user->id)->find($id);

        if (!$key) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $key->id,
                'name' => $key->name,
                'key_prefix' => $key->key_prefix,
                'company_id' => $key->company_id,
                'scopes' => $key->scopes,
                'is_active' => $key->is_active,
                'last_used_at' => $key->last_used_at?->toIso8601String(),
                'expires_at' => $key->expires_at?->toIso8601String(),
                'created_at' => $key->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update an API key (name, scopes, or active status).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $key = ApiKey::forUser($user->id)->find($id);

        if (!$key) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'scopes' => 'sometimes|array',
                'scopes.*' => 'string',
                'is_active' => 'sometimes|boolean',
            ]);

            $key->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'API key updated successfully',
                'data' => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'scopes' => $key->scopes,
                    'is_active' => $key->is_active,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Revoke (delete) an API key.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $key = ApiKey::forUser($user->id)->find($id);

        if (!$key) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found',
            ], 404);
        }

        $key->delete();

        return response()->json([
            'success' => true,
            'message' => 'API key revoked successfully',
        ]);
    }

    /**
     * Regenerate an API key (creates new key, keeps same ID).
     */
    public function regenerate(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $key = ApiKey::forUser($user->id)->find($id);

        if (!$key) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found',
            ], 404);
        }

        // Generate new key values
        $prefix = 'bsk_' . \Illuminate\Support\Str::random(8);
        $secret = \Illuminate\Support\Str::random(32);
        $plainKey = $prefix . '_' . $secret;

        $key->update([
            'key_hash' => \Illuminate\Support\Facades\Hash::make($plainKey),
            'key_prefix' => $prefix,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key regenerated successfully. Save this key - it will not be shown again.',
            'data' => [
                'id' => $key->id,
                'name' => $key->name,
                'key' => $plainKey, // Only returned once!
                'key_prefix' => $key->key_prefix,
            ],
        ]);
    }
}

