# Server Deployment Configuration Guide

This guide covers all the configuration files that need to be modified when deploying to production server.

---

## Quick Checklist

Before deploying, ensure you've updated these files:

| File | Location | Required Changes |
|------|----------|------------------|
| `.env` | `backend/.env` | Production values for all env vars |
| `cors.php` | `backend/config/cors.php` | Add production domains |
| `.env` | `apps/portal/.env` | Production API URL & Supabase |
| `.env` | `apps/client/.env` | Production API URL & Supabase |
| `.env.local` | `apps/marketing/.env.local` | Production API URL |
| Supabase Dashboard | - | Add production redirect URLs |

---

## 1. Backend Configuration

### 1.1 Backend `.env` File

Create/edit `backend/.env` with production values:

```env
# ============================================
# APPLICATION SETTINGS
# ============================================
APP_NAME="Your App Name"
APP_ENV=production          # CHANGE: from 'local' to 'production'
APP_KEY=base64:...         # KEEP: your existing key or generate new
APP_DEBUG=false            # CHANGE: must be false in production!
APP_URL=https://api.yourdomain.com  # CHANGE: your production API URL

# ============================================
# DATABASE (Supabase PostgreSQL)
# ============================================
DB_CONNECTION=pgsql
DB_HOST=db.YOUR_PROJECT_REF.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-database-password

# ============================================
# SUPABASE
# ============================================
SUPABASE_URL=https://YOUR_PROJECT_REF.supabase.co
SUPABASE_KEY=your-supabase-anon-key
SUPABASE_SERVICE_ROLE_KEY=your-supabase-service-role-key
SUPABASE_PROJECT_REF=YOUR_PROJECT_REF

# ============================================
# REDIS (Railway or other provider)
# ============================================
REDIS_CLIENT=phpredis
REDIS_HOST=your-redis-host.railway.app
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379

# ============================================
# CACHE, QUEUE & SESSION
# ============================================
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_DOMAIN=.yourdomain.com    # IMPORTANT: Use dot prefix for subdomains
SESSION_SECURE_COOKIE=true       # IMPORTANT: Must be true for HTTPS
SESSION_SAME_SITE=none           # CHANGE: 'none' for cross-origin SPA

# ============================================
# SANCTUM (SPA Authentication)
# ============================================
# CRITICAL: List all your frontend domains (comma-separated, no spaces)
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,portal.yourdomain.com,app.yourdomain.com,api.yourdomain.com

# ============================================
# BROADCASTING (Soketi)
# ============================================
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-soketi-app-id
PUSHER_APP_KEY=your-soketi-app-key
PUSHER_APP_SECRET=your-soketi-app-secret
PUSHER_HOST=your-soketi-host.railway.app
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

# ============================================
# BUNNY CDN (Storage)
# ============================================
BUNNY_STORAGE_ZONE=your-storage-zone
BUNNY_STORAGE_KEY=your-storage-key
BUNNY_CDN_URL=https://your-pullzone.b-cdn.net
BUNNY_STREAM_LIBRARY_ID=your-library-id
BUNNY_STREAM_API_KEY=your-stream-api-key
```

### 1.2 CORS Configuration (`backend/config/cors.php`)

**⚠️ MUST EDIT THIS FILE** - Add your production domains:

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Development (can remove in production)
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:3002',
        
        // Production - ADD YOUR DOMAINS HERE
        'https://yourdomain.com',           // Marketing site
        'https://portal.yourdomain.com',    // Portal app
        'https://app.yourdomain.com',       // Client app
        // Or if using different domains:
        // 'https://your-portal-domain.com',
        // 'https://your-client-domain.com',
    ],

    'allowed_origins_patterns' => [
        // Optional: Use patterns for dynamic subdomains
        // 'https://*.yourdomain.com',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,  // IMPORTANT: Keep this true for cookies
];
```

### 1.3 Session Cookie Configuration

These are set via `.env` but here's what happens:

| Setting | Development | Production |
|---------|-------------|------------|
| `SESSION_DOMAIN` | `localhost` | `.yourdomain.com` (with dot prefix) |
| `SESSION_SECURE_COOKIE` | `false` | `true` |
| `SESSION_SAME_SITE` | `lax` | `none` |

**Why the dot prefix?** - Using `.yourdomain.com` allows the session cookie to be shared across subdomains (api.yourdomain.com, portal.yourdomain.com, etc.)

### 1.4 Sanctum Configuration

Already configured via `.env`, but ensure `SANCTUM_STATEFUL_DOMAINS` includes ALL frontend domains that need authentication:

```
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,portal.yourdomain.com,app.yourdomain.com,api.yourdomain.com
```

**Note:** Include the API domain too if you're making authenticated requests from there.

---

## 2. Frontend Configuration

### 2.1 Portal App (`apps/portal/.env`)

```env
# Supabase Configuration
VITE_SUPABASE_URL=https://YOUR_PROJECT_REF.supabase.co
VITE_SUPABASE_ANON_KEY=your-supabase-anon-key

# API Configuration - CHANGE TO PRODUCTION
VITE_API_URL=https://api.yourdomain.com

# WebSocket Configuration
VITE_PUSHER_APP_KEY=your-soketi-app-key
VITE_PUSHER_HOST=your-soketi-host.railway.app
VITE_PUSHER_PORT=443
VITE_PUSHER_SCHEME=https
VITE_PUSHER_APP_CLUSTER=mt1
```

### 2.2 Client App (`apps/client/.env`)

```env
# Supabase Configuration
VITE_SUPABASE_URL=https://YOUR_PROJECT_REF.supabase.co
VITE_SUPABASE_ANON_KEY=your-supabase-anon-key

# API Configuration - CHANGE TO PRODUCTION
VITE_API_URL=https://api.yourdomain.com

# WebSocket Configuration
VITE_PUSHER_APP_KEY=your-soketi-app-key
VITE_PUSHER_HOST=your-soketi-host.railway.app
VITE_PUSHER_PORT=443
VITE_PUSHER_SCHEME=https
VITE_PUSHER_APP_CLUSTER=mt1
```

### 2.3 Marketing App (`apps/marketing/.env.local`)

```env
# API Configuration - CHANGE TO PRODUCTION
NEXT_PUBLIC_API_URL=https://api.yourdomain.com

# Optional
# NEXT_PUBLIC_GA_ID=your-google-analytics-id
```

---

## 3. Supabase Dashboard Configuration

### 3.1 Auth URL Configuration

Go to **Authentication → URL Configuration** in Supabase Dashboard:

| Setting | Production Value |
|---------|------------------|
| Site URL | `https://portal.yourdomain.com` |
| Redirect URLs | See below |

**Add these Redirect URLs:**
```
https://yourdomain.com/**
https://portal.yourdomain.com/**
https://app.yourdomain.com/**
```

### 3.2 Email Templates

If using custom domain, update email template links in **Authentication → Email Templates** to use your production URLs.

---

## 4. Domain & SSL Setup

### 4.1 Recommended Domain Structure

| Subdomain | App | Notes |
|-----------|-----|-------|
| `yourdomain.com` | Marketing site | Main landing page |
| `portal.yourdomain.com` | Portal app | Admin/Staff dashboard |
| `app.yourdomain.com` | Client app | Customer portal |
| `api.yourdomain.com` | Laravel API | Backend API |

### 4.2 Alternative: Separate Domains

If using separate domains instead of subdomains:
- Update `cors.php` with each domain
- Update `SANCTUM_STATEFUL_DOMAINS` with each domain
- Update Supabase redirect URLs

### 4.3 SSL Certificates

All domains **MUST** have valid SSL certificates for:
- Secure cookies (`SESSION_SECURE_COOKIE=true`)
- SameSite=None cookies to work
- Supabase authentication

---

## 5. Server-Specific Settings

### 5.1 After First Deploy Commands

```bash
cd backend

# Generate new app key if needed
php artisan key:generate

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (if needed)
php artisan migrate --force
```

### 5.2 Queue Worker

Set up a supervisor or process manager to run:

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

### 5.3 Scheduler (Cron)

Add to server crontab:

```bash
* * * * * cd /path-to-your-project/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Common Issues & Solutions

### Issue: CORS Errors
**Solution:** Check `cors.php` has your frontend domain with exact protocol (https://)

### Issue: Authentication Not Working (401 Errors)
**Solutions:**
1. Check `SANCTUM_STATEFUL_DOMAINS` includes your domain
2. Check `SESSION_DOMAIN` has correct value with dot prefix
3. Check `SESSION_SECURE_COOKIE=true` for HTTPS
4. Check `SESSION_SAME_SITE=none` for cross-origin

### Issue: Cookies Not Being Set
**Solutions:**
1. Verify SSL is working on all domains
2. Check browser console for cookie warnings
3. Ensure `supports_credentials` is `true` in `cors.php`

### Issue: Supabase Auth Redirect Fails
**Solution:** Add production URLs to Supabase Dashboard → Authentication → URL Configuration

### Issue: WebSocket Connection Fails
**Solutions:**
1. Check Soketi/Pusher credentials match between backend and frontend
2. Verify WebSocket server is running
3. Check firewall allows WebSocket connections

---

## 7. Security Checklist

Before going live, verify:

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Strong database password
- [ ] SSL certificates on all domains
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] CORS only allows your domains (remove localhost in production if not needed)
- [ ] Service role key is kept secret (never exposed to frontend)
- [ ] Redis password is set
- [ ] Backup strategy in place

---

## 8. Quick Reference: Files to Modify

```
📁 backend/
├── .env                    ← Main production configuration
├── config/
│   └── cors.php           ← Add production domains to allowed_origins

📁 apps/
├── portal/.env            ← Production API URL & Supabase
├── client/.env            ← Production API URL & Supabase
└── marketing/.env.local   ← Production API URL

📁 Supabase Dashboard
└── Authentication → URL Configuration  ← Add redirect URLs
```

---

## 9. Example: Full Production Values

Assuming your domain is `mycrm.com`:

### Backend `.env` Key Values:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.mycrm.com
SESSION_DOMAIN=.mycrm.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
SANCTUM_STATEFUL_DOMAINS=mycrm.com,portal.mycrm.com,app.mycrm.com,api.mycrm.com
```

### CORS allowed_origins:
```php
'allowed_origins' => [
    'https://mycrm.com',
    'https://portal.mycrm.com',
    'https://app.mycrm.com',
],
```

### Frontend VITE_API_URL:
```env
VITE_API_URL=https://api.mycrm.com
```

### Supabase Redirect URLs:
```
https://mycrm.com/**
https://portal.mycrm.com/**
https://app.mycrm.com/**
```



