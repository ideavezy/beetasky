<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', config('app.name'))</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        /* Base styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #2a2d3a;
        }
        /* Link colors */
        a {
            color: #d4a853;
        }
        /* Button hover (for email clients that support it) */
        .btn-primary:hover {
            background-color: #c49743 !important;
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: 'Poppins', 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #2a2d3a; -webkit-font-smoothing: antialiased;">
    
    <!-- Preheader (hidden preview text) -->
    @hasSection('preheader')
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        @yield('preheader')
    </div>
    @endif

    <!-- Email wrapper -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #2a2d3a;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                
                <!-- Main container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #3a3d4a; border-radius: 12px; overflow: hidden;">
                    
                    <!-- Header with Logo -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 30px 40px; background-color: #32353f;">
                            @hasSection('logo')
                                @yield('logo')
                            @else
                                <img src="{{ config('app.url') }}/brand/logo-white.webp" alt="{{ config('app.name') }}" width="180" style="display: block; max-width: 180px; height: auto;">
                            @endif
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            @yield('content')
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #32353f; border-top: 1px solid #4a4d5a;">
                            @hasSection('footer')
                                @yield('footer')
                            @else
                                <p style="margin: 0; font-size: 12px; color: #6b6e7a; text-align: center;">
                                    © {{ date('Y') }} {{ config('app.name') }} • This is an automated message, please do not reply.
                                </p>
                            @endif
                        </td>
                    </tr>
                    
                </table>
                
                <!-- Additional footer links (optional) -->
                @hasSection('footer_links')
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; margin-top: 20px;">
                    <tr>
                        <td align="center">
                            @yield('footer_links')
                        </td>
                    </tr>
                </table>
                @endif
                
            </td>
        </tr>
    </table>
    
</body>
</html>

