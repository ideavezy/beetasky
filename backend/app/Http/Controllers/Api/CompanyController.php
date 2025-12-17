<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\UserPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    /**
     * Get all companies for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $companies = $user->companies()
            ->orderBy('name')
            ->get()
            ->map(fn($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo_url' => $company->logo_url,
                'billing_status' => $company->billing_status,
                'role' => $company->pivot->role_in_company,
                'is_active' => $company->pivot->is_active,
                'created_at' => $company->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

    /**
     * Create a new company.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|min:2',
            ]);

            // Generate unique slug
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            
            while (Company::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Create the company
            $company = Company::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'owner_id' => $user->id,
                'billing_status' => 'trial',
                'billing_cycle' => 'monthly',
            ]);

            // Add the user as owner (with UUID for pivot)
            DB::table('company_user')->insert([
                'id' => Str::uuid()->toString(),
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role_in_company' => 'owner',
                'permissions' => json_encode(['*']),
                'joined_at' => now(),
            ]);

            // Set this company as the user's default company
            $preset = UserPreset::firstOrCreate(
                ['user_id' => $user->id],
                ['settings' => []]
            );
            $preset->update(['default_company_id' => $company->id]);

            return response()->json([
                'success' => true,
                'message' => 'Company created successfully',
                'data' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'logo_url' => $company->logo_url,
                    'billing_status' => $company->billing_status,
                    'role' => 'owner',
                    'is_active' => true,
                    'created_at' => $company->created_at,
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
                'message' => 'Failed to create company',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get a specific company.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $company = $user->companies()->find($id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo_url' => $company->logo_url,
                'billing_status' => $company->billing_status,
                'billing_cycle' => $company->billing_cycle,
                'settings' => $company->settings,
                'role' => $company->pivot->role_in_company,
                'is_active' => $company->pivot->is_active,
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
            ],
        ]);
    }

    /**
     * Update a company.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $company = $user->companies()
            ->wherePivotIn('role_in_company', ['owner', 'manager'])
            ->find($id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or insufficient permissions',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|min:2',
                'logo_url' => 'nullable|url|max:500',
                'settings' => 'nullable|array',
            ]);

            $company->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully',
                'data' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'logo_url' => $company->logo_url,
                    'billing_status' => $company->billing_status,
                    'settings' => $company->settings,
                    'updated_at' => $company->updated_at,
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
}

