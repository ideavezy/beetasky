<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserPresetController extends Controller
{
    /**
     * Get the current user's presets.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get or create presets for this user
        $preset = $user->getOrCreatePreset();
        $preset->load('defaultCompany');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $preset->id,
                'default_company_id' => $preset->default_company_id,
                'default_company' => $preset->defaultCompany ? [
                    'id' => $preset->defaultCompany->id,
                    'name' => $preset->defaultCompany->name,
                    'slug' => $preset->defaultCompany->slug,
                    'logo_url' => $preset->defaultCompany->logo_url,
                ] : null,
                'settings' => $preset->settings,
                'created_at' => $preset->created_at,
                'updated_at' => $preset->updated_at,
            ],
        ]);
    }

    /**
     * Update the current user's presets.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'default_company_id' => 'nullable|uuid|exists:companies,id',
                'settings' => 'nullable|array',
            ]);

            // Verify user has access to the company if setting default
            if (!empty($validated['default_company_id'])) {
                $hasAccess = $user->companies()
                    ->where('companies.id', $validated['default_company_id'])
                    ->exists();

                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this company',
                    ], 403);
                }
            }

            // Get or create presets
            $preset = $user->getOrCreatePreset();

            // Update preset
            $updateData = [];
            if (array_key_exists('default_company_id', $validated)) {
                $updateData['default_company_id'] = $validated['default_company_id'];
            }
            if (array_key_exists('settings', $validated)) {
                // Merge settings instead of replacing
                $updateData['settings'] = array_merge(
                    $preset->settings ?? [],
                    $validated['settings'] ?? []
                );
            }

            if (!empty($updateData)) {
                $preset->update($updateData);
            }

            $preset->load('defaultCompany');

            return response()->json([
                'success' => true,
                'message' => 'Presets updated successfully',
                'data' => [
                    'id' => $preset->id,
                    'default_company_id' => $preset->default_company_id,
                    'default_company' => $preset->defaultCompany ? [
                        'id' => $preset->defaultCompany->id,
                        'name' => $preset->defaultCompany->name,
                        'slug' => $preset->defaultCompany->slug,
                        'logo_url' => $preset->defaultCompany->logo_url,
                    ] : null,
                    'settings' => $preset->settings,
                    'updated_at' => $preset->updated_at,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update presets',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}


