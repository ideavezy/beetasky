<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supabase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Supabase database and realtime features.
    | Laravel will use Supabase PostgreSQL as its database connection.
    |
    */

    'url' => env('SUPABASE_URL', 'https://klejvjrtlglmmdtphykp.supabase.co'),
    
    'key' => env('SUPABASE_KEY', 'sb_secret_tqnJ6MFNWOzfJxj2JZehmg_sibU439r'),
    
    'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY', ''),
    
    // JWT secret for validating Supabase auth tokens
    // Get this from: Supabase Dashboard > Project Settings > API > JWT Secret
    'jwt_secret' => env('SUPABASE_JWT_SECRET', ''),
    
    /*
    |--------------------------------------------------------------------------
    | Management API
    |--------------------------------------------------------------------------
    |
    | For programmatic access to project settings (email templates, etc.)
    | Get your access token from: https://supabase.com/dashboard/account/tokens
    | Get your project ref from your project URL
    |
    */
    
    'management_api_token' => env('SUPABASE_MANAGEMENT_API_TOKEN', ''),
    
    'project_ref' => env('SUPABASE_PROJECT_REF', 'klejvjrtlglmmdtphykp'),
    
    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | Supabase uses PostgreSQL. Configure these in your .env file:
    | DB_CONNECTION=pgsql
    | DB_HOST=db.YOUR_PROJECT_REF.supabase.co
    | DB_PORT=5432
    | DB_DATABASE=postgres
    | DB_USERNAME=postgres
    | DB_PASSWORD=your-database-password
    |
    */
    
    'database' => [
        'host' => env('DB_HOST', 'db.klejvjrtlglmmdtphykp.supabase.co'),
        'port' => env('DB_PORT', 5432),
        'database' => env('DB_DATABASE', 'postgres'),
        'username' => env('DB_USERNAME', 'postgres'),
        'password' => env('DB_PASSWORD', 'V6iDXoo8jCaO8IVI'),
    ],
];

