<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class SyncEmailTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supabase:sync-email-templates 
                            {--dry-run : Show what would be sent without actually sending}
                            {--template= : Sync only a specific template (e.g., confirmation, invite, magic_link, recovery, email_change, reauthentication)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync email templates to Supabase via Management API';

    /**
     * Email template configurations.
     * Maps Supabase config keys to local template files and subjects.
     * 
     * Note: Supabase Management API only supports these 5 templates:
     * - confirmation (Confirm signup / Confirm Email)
     * - invite (Invite user)
     * - magic_link (Magic Link)
     * - recovery (Reset Password)
     * - email_change (Change Email Address)
     * - reauthentication (Reauth)
     * 
     * The other notification emails (password changed, email changed, phone changed,
     * identity linked/unlinked, MFA added/removed) are handled via Auth Hooks
     * and require a custom email provider setup.
     */
    protected array $templates = [
        'confirmation' => [
            'file' => 'confirm-email.html',
            'subject' => 'Confirm Your Email - BeeMud',
        ],
        'invite' => [
            'file' => 'invite-user.html',
            'subject' => "You've Been Invited - BeeMud",
        ],
        'magic_link' => [
            'file' => 'magic-link.html',
            'subject' => 'Your Magic Link - BeeMud',
        ],
        'recovery' => [
            'file' => 'reset-password.html',
            'subject' => 'Reset Your Password - BeeMud',
        ],
        'email_change' => [
            'file' => 'change-email.html',
            'subject' => 'Confirm Email Change - BeeMud',
        ],
        'reauthentication' => [
            'file' => 'reauthentication.html',
            'subject' => 'Confirm Your Identity - BeeMud',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accessToken = config('supabase.management_api_token');
        $projectRef = config('supabase.project_ref');

        if (!$accessToken || !$projectRef) {
            $this->error('Missing SUPABASE_MANAGEMENT_API_TOKEN or SUPABASE_PROJECT_REF in your environment.');
            $this->line('');
            $this->info('To get these values:');
            $this->line('1. Get your access token from: https://supabase.com/dashboard/account/tokens');
            $this->line('2. Get your project ref from your project URL: https://supabase.com/dashboard/project/<project-ref>');
            $this->line('');
            $this->line('Add these to your .env file:');
            $this->line('SUPABASE_MANAGEMENT_API_TOKEN=your-access-token');
            $this->line('SUPABASE_PROJECT_REF=your-project-ref');
            return Command::FAILURE;
        }

        $templateDir = resource_path('email-templates');
        $isDryRun = $this->option('dry-run');
        $specificTemplate = $this->option('template');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        // Filter templates if specific one requested
        $templatesToSync = $this->templates;
        if ($specificTemplate) {
            if (!isset($this->templates[$specificTemplate])) {
                $this->error("Unknown template: {$specificTemplate}");
                $this->line('');
                $this->info('Available templates:');
                foreach (array_keys($this->templates) as $key) {
                    $this->line("  - {$key}");
                }
                return Command::FAILURE;
            }
            $templatesToSync = [$specificTemplate => $this->templates[$specificTemplate]];
        }

        // Build the payload
        $payload = [];
        $this->info('Reading email templates...');
        $this->line('');

        foreach ($templatesToSync as $key => $config) {
            $filePath = "{$templateDir}/{$config['file']}";
            
            if (!File::exists($filePath)) {
                $this->warn("  ✗ Template file not found: {$config['file']}");
                continue;
            }

            $content = File::get($filePath);
            
            // Minify HTML (remove extra whitespace but preserve structure)
            $content = $this->minifyHtml($content);
            
            // Add to payload
            $payload["mailer_subjects_{$key}"] = $config['subject'];
            $payload["mailer_templates_{$key}_content"] = $content;
            
            $this->info("  ✓ {$key}");
            $this->line("    Subject: {$config['subject']}");
            $this->line("    File: {$config['file']} (" . strlen($content) . " bytes)");
        }

        if (empty($payload)) {
            $this->error('No templates to sync!');
            return Command::FAILURE;
        }

        $this->line('');
        
        if ($isDryRun) {
            $this->info('Would send the following payload to Supabase:');
            $this->line('');
            foreach ($payload as $key => $value) {
                if (str_contains($key, '_content')) {
                    $this->line("  {$key}: [HTML content - " . strlen($value) . " bytes]");
                } else {
                    $this->line("  {$key}: {$value}");
                }
            }
            $this->line('');
            $this->info('Run without --dry-run to apply changes.');
            return Command::SUCCESS;
        }

        // Send to Supabase Management API
        $this->info('Syncing to Supabase...');
        
        try {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->patch(
                    "https://api.supabase.com/v1/projects/{$projectRef}/config/auth",
                    $payload
                );

            if ($response->successful()) {
                $this->line('');
                $this->info('✓ Email templates synced successfully!');
                return Command::SUCCESS;
            }

            $this->line('');
            $this->error('Failed to sync templates.');
            $this->line('');
            $this->line('Status: ' . $response->status());
            $this->line('Response: ' . $response->body());
            return Command::FAILURE;
            
        } catch (\Exception $e) {
            $this->error('Error syncing templates: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Minify HTML content while preserving structure.
     */
    protected function minifyHtml(string $html): string
    {
        // Remove HTML comments
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);
        
        // Remove extra whitespace between tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Normalize whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        return trim($html);
    }
}



