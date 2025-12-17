<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin 
                            {email : The email address for the admin user}
                            {password : The password for the admin user}
                            {--name= : Optional full name for the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin user in Supabase Auth';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');
        $name = $this->option('name') ?? 'Admin User';

        $supabaseUrl = config('supabase.url');
        $serviceRoleKey = config('supabase.service_role_key');

        if (!$serviceRoleKey) {
            $this->error('SUPABASE_SERVICE_ROLE_KEY is not configured in your .env file.');
            $this->line('');
            $this->info('Get your service role key from:');
            $this->line('https://supabase.com/dashboard/project/_/settings/api');
            return Command::FAILURE;
        }

        $this->info("Creating admin user: {$email}");

        try {
            // Create user via Supabase Admin API
            $response = Http::withHeaders([
                'apikey' => $serviceRoleKey,
                'Authorization' => "Bearer {$serviceRoleKey}",
                'Content-Type' => 'application/json',
            ])->post("{$supabaseUrl}/auth/v1/admin/users", [
                'email' => $email,
                'password' => $password,
                'email_confirm' => true, // Auto-confirm the email
                'user_metadata' => [
                    'full_name' => $name,
                ],
                'app_metadata' => [
                    'role' => 'admin',
                ],
            ]);

            if ($response->successful()) {
                $user = $response->json();
                $this->line('');
                $this->info('✓ Admin user created successfully!');
                $this->line('');
                $this->line("  User ID: {$user['id']}");
                $this->line("  Email: {$user['email']}");
                $this->line("  Role: admin");
                $this->line('');
                $this->warn('Important: Update the user\'s global_role in the database:');
                $this->line("  UPDATE users SET global_role = 'admin' WHERE id = '{$user['id']}';");
                
                return Command::SUCCESS;
            }

            $error = $response->json();
            $this->error('Failed to create user.');
            $this->line('');
            $this->line('Status: ' . $response->status());
            $this->line('Error: ' . ($error['msg'] ?? $error['message'] ?? json_encode($error)));
            
            return Command::FAILURE;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}



