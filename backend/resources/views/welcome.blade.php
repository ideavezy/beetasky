<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>BeetaSky API</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0d0d0d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a0a0a0;
            padding: 24px;
        }
        
        .container {
            text-align: center;
            max-width: 480px;
        }
        
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 32px;
            background: linear-gradient(135deg, #d4a855 0%, #b8942e 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(212, 168, 85, 0.15);
        }
        
        .icon svg {
            width: 40px;
            height: 40px;
            color: #0a0a0a;
        }
        
        h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 12px;
        }
        
        .subtitle {
            font-size: 1rem;
            color: #666666;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        .divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #333333, transparent);
            margin: 0 auto 32px;
        }
        
        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .info-box p {
            font-size: 0.875rem;
            line-height: 1.7;
            color: #888888;
        }
        
        .api-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(212, 168, 85, 0.1);
            border: 1px solid rgba(212, 168, 85, 0.2);
            color: #d4a855;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .api-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #d4a855;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .footer {
            margin-top: 48px;
            font-size: 0.75rem;
            color: #444444;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
            </svg>
        </div>
        
        <h1>BeetaSky API Server</h1>
        <p class="subtitle">This is the backend API endpoint</p>
        
        <div class="divider"></div>
        
        <div class="info-box">
            <p>
                You've reached the BeetaSky API server. This endpoint is designed for 
                application-to-application communication and is not meant for direct browser access.
            </p>
        </div>
        
        <span class="api-badge">API Active</span>
        
        <div class="footer">
            &copy; {{ date('Y') }} BeetaSky. All rights reserved.
        </div>
    </div>
</body>
</html>
