<?php

namespace App\Services;

use App\Mail\PortalInvitation;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Contact Invitation Service
 * 
 * Handles inviting contacts to the client portal.
 * Uses Supabase Admin API to create users and Laravel Mail to send invitations.
 */
class ContactInvitationService
{
    protected string $supabaseUrl;
    protected string $serviceRoleKey;
    protected string $clientUrl;

    public function __construct()
    {
        $this->supabaseUrl = config('supabase.url');
        $this->serviceRoleKey = config('supabase.service_role_key');
        $this->clientUrl = config('app.client_url', config('app.url'));

        // Validate that required configuration is present
        if (empty($this->serviceRoleKey)) {
            Log::warning('SUPABASE_SERVICE_ROLE_KEY is not configured. Contact invitations will fail.');
        }
    }

    /**
     * Check if the service is properly configured
     */
    protected function validateConfiguration(): array
    {
        if (empty($this->supabaseUrl)) {
            return [
                'success' => false,
                'message' => 'Supabase URL is not configured. Please set SUPABASE_URL in your .env file.',
            ];
        }

        if (empty($this->serviceRoleKey)) {
            return [
                'success' => false,
                'message' => 'Supabase Service Role Key is not configured. Please set SUPABASE_SERVICE_ROLE_KEY in your .env file.',
            ];
        }

        return ['success' => true];
    }

    /**
     * Invite a contact to the client portal.
     * 
     * @param Contact $contact The contact to invite
     * @param string $companyId The company inviting the contact
     * @return array Result with success status and message
     */
    public function inviteContact(Contact $contact, string $companyId): array
    {
        // Validate configuration first
        $configCheck = $this->validateConfiguration();
        if (!$configCheck['success']) {
            return $configCheck;
        }

        // Validate contact has email
        if (empty($contact->email)) {
            return [
                'success' => false,
                'message' => 'Contact must have an email address to be invited',
            ];
        }

        // Check if contact already has a user account
        if ($contact->hasUserAccount()) {
            return [
                'success' => false,
                'message' => 'Contact already has a portal account',
                'already_invited' => true,
            ];
        }

        try {
            // Check if a user with this email already exists
            $existingUser = User::where('email', $contact->email)->first();

            if ($existingUser) {
                // Link existing user to contact
                $contact->update(['auth_user_id' => $existingUser->id]);

                return [
                    'success' => true,
                    'message' => 'Contact linked to existing portal account',
                    'user_id' => $existingUser->id,
                ];
            }

            // Get company name for the email
            $company = Company::find($companyId);
            $companyName = $company?->name ?? config('app.name');

            // Create user in Supabase (generate magic link for password setup)
            $response = $this->createUserInSupabase($contact);

            if (!$response['success']) {
                return $response;
            }

            $supabaseUserId = $response['user_id'];
            $confirmationUrl = $response['confirmation_url'] ?? null;
            $token = $response['token'] ?? null;

            // Check if user already exists in local DB (could happen if previous invite partially completed)
            $user = User::find($supabaseUserId);
            
            if (!$user) {
                // Create local user record
                $nameParts = explode(' ', $contact->full_name, 2);
                $user = User::create([
                    'id' => $supabaseUserId,
                    'email' => $contact->email,
                    'first_name' => $nameParts[0] ?? 'User',
                    'last_name' => $nameParts[1] ?? '',
                    'phone' => $contact->phone,
                    'avatar_url' => $contact->avatar_url,
                    'global_role' => 'user',
                ]);
            }

            // Link contact to user
            $contact->update(['auth_user_id' => $user->id]);

            // Update company_contact relationship
            DB::table('company_contacts')
                ->where('company_id', $companyId)
                ->where('contact_id', $contact->id)
                ->update([
                    'status' => 'active',
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);

            // Send invitation email via Laravel Mail
            $this->sendInvitationEmail($contact, $confirmationUrl, $token, $companyName);

            Log::info('Contact invited to portal', [
                'contact_id' => $contact->id,
                'user_id' => $user->id,
                'email' => $contact->email,
            ]);

            return [
                'success' => true,
                'message' => 'Invitation sent successfully',
                'user_id' => $user->id,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to invite contact', [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create user in Supabase Admin API (without sending email)
     * Generate a magic link that the user can use to set up their account
     */
    protected function createUserInSupabase(Contact $contact): array
    {
        $nameParts = explode(' ', $contact->full_name, 2);

        // First, try to create the user with admin API
        $response = Http::withHeaders([
            'apikey' => $this->serviceRoleKey,
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'Content-Type' => 'application/json',
        ])->post("{$this->supabaseUrl}/auth/v1/admin/users", [
            'email' => $contact->email,
            'email_confirm' => false, // Don't auto-confirm, they need to accept invitation
            'user_metadata' => [
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
                'phone' => $contact->phone,
                'invited_as' => 'contact',
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $userId = $data['id'] ?? null;

            if ($userId) {
                // Generate a magic link for the user to accept the invitation
                $magicLinkResponse = $this->generateMagicLink($contact->email);
                
                return [
                    'success' => true,
                    'user_id' => $userId,
                    'confirmation_url' => $magicLinkResponse['url'] ?? $this->buildInviteUrl($contact->email),
                    'token' => $magicLinkResponse['token'] ?? null,
                ];
            }
        }

        // Handle specific error cases
        $errorBody = $response->json();
        $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? $errorBody['msg'] ?? 'Unknown error';

        // If user already exists in Supabase but not in our DB
        if (str_contains(strtolower($errorMessage), 'already registered') || 
            str_contains(strtolower($errorMessage), 'already exists') ||
            str_contains(strtolower($errorMessage), 'duplicate')) {
            
            // Try to get the existing user from Supabase
            $existingUser = $this->getSupabaseUserByEmail($contact->email);
            
            if ($existingUser) {
                $magicLinkResponse = $this->generateMagicLink($contact->email);
                
                return [
                    'success' => true,
                    'user_id' => $existingUser['id'],
                    'existing' => true,
                    'confirmation_url' => $magicLinkResponse['url'] ?? $this->buildInviteUrl($contact->email),
                    'token' => $magicLinkResponse['token'] ?? null,
                ];
            }
        }

        Log::error('Supabase user creation failed', [
            'email' => $contact->email,
            'status' => $response->status(),
            'response' => $errorBody,
        ]);

        return [
            'success' => false,
            'message' => $errorMessage,
        ];
    }

    /**
     * Generate a magic link for user authentication
     */
    protected function generateMagicLink(string $email): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->serviceRoleKey,
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'Content-Type' => 'application/json',
        ])->post("{$this->supabaseUrl}/auth/v1/admin/generate_link", [
            'type' => 'invite',
            'email' => $email,
            'options' => [
                'redirect_to' => $this->clientUrl . '/auth/callback',
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            // Use email_otp (6-digit code) instead of hashed_token (long hash)
            // email_otp is more user-friendly for manual entry
            $otp = $data['email_otp'] ?? null;
            
            return [
                'url' => $data['action_link'] ?? null,
                'token' => $otp,
            ];
        }

        Log::warning('Failed to generate magic link', [
            'email' => $email,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return [];
    }

    /**
     * Build a fallback invite URL
     */
    protected function buildInviteUrl(string $email): string
    {
        return $this->clientUrl . '/auth/accept-invite?' . http_build_query([
            'email' => $email,
        ]);
    }

    /**
     * Send invitation email using Laravel Mail
     */
    protected function sendInvitationEmail(
        Contact $contact, 
        ?string $confirmationUrl, 
        ?string $token, 
        string $companyName
    ): void {
        try {
            Mail::to($contact->email)->send(new PortalInvitation(
                recipientName: $contact->full_name,
                recipientEmail: $contact->email,
                confirmationUrl: $confirmationUrl ?? $this->buildInviteUrl($contact->email),
                token: $token,
                companyName: $companyName,
            ));

            Log::info('Invitation email sent', [
                'email' => $contact->email,
                'mailer' => config('mail.default'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'email' => $contact->email,
                'error' => $e->getMessage(),
            ]);
            
            // Re-throw so the parent method knows the email failed
            throw $e;
        }
    }

    /**
     * Get Supabase user by email
     */
    protected function getSupabaseUserByEmail(string $email): ?array
    {
        $response = Http::withHeaders([
            'apikey' => $this->serviceRoleKey,
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'Content-Type' => 'application/json',
        ])->get("{$this->supabaseUrl}/auth/v1/admin/users", [
            'filter' => "email.eq.{$email}",
        ]);

        if ($response->successful()) {
            $users = $response->json()['users'] ?? $response->json();
            
            if (is_array($users) && count($users) > 0) {
                return $users[0];
            }
        }

        return null;
    }

    /**
     * Resend invitation to a contact
     */
    public function resendInvitation(Contact $contact, ?string $companyId = null): array
    {
        // Validate configuration first
        $configCheck = $this->validateConfiguration();
        if (!$configCheck['success']) {
            return $configCheck;
        }

        if (!$contact->hasUserAccount()) {
            return [
                'success' => false,
                'message' => 'Contact has not been invited yet',
            ];
        }

        if (empty($contact->email)) {
            return [
                'success' => false,
                'message' => 'Contact must have an email address',
            ];
        }

        try {
            Log::info('Resending invitation', [
                'contact_id' => $contact->id,
                'email' => $contact->email,
            ]);

            // Get company name for the email
            $companyName = config('app.name');
            if ($companyId) {
                $company = Company::find($companyId);
                $companyName = $company?->name ?? $companyName;
            }

            // Generate a new magic link
            $magicLinkResponse = $this->generateMagicLink($contact->email);
            
            $confirmationUrl = $magicLinkResponse['url'] ?? $this->buildInviteUrl($contact->email);
            $token = $magicLinkResponse['token'] ?? null;

            Log::info('Generated magic link for resend', [
                'email' => $contact->email,
                'has_confirmation_url' => !empty($confirmationUrl),
                'has_token' => !empty($token),
            ]);

            // Send the invitation email
            $this->sendInvitationEmail($contact, $confirmationUrl, $token, $companyName);

            Log::info('Invitation resent successfully', [
                'email' => $contact->email,
            ]);

            return [
                'success' => true,
                'message' => 'Invitation resent successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to resend invitation', [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to resend invitation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check invitation status for a contact
     */
    public function getInvitationStatus(Contact $contact): array
    {
        if (!$contact->hasUserAccount()) {
            return [
                'status' => 'not_invited',
                'message' => 'Contact has not been invited',
            ];
        }

        $user = $contact->user;

        if (!$user) {
            return [
                'status' => 'pending',
                'message' => 'Invitation pending',
            ];
        }

        if ($user->email_verified_at) {
            return [
                'status' => 'accepted',
                'message' => 'Invitation accepted',
                'accepted_at' => $user->email_verified_at,
            ];
        }

        return [
            'status' => 'pending',
            'message' => 'Invitation sent, awaiting acceptance',
        ];
    }
}

