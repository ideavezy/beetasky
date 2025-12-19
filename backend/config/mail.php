<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        /*
        |--------------------------------------------------------------------------
        | SMTP2GO Mailer
        |--------------------------------------------------------------------------
        |
        | SMTP2GO configuration using their SMTP relay service.
        | Set MAIL_MAILER=smtp2go in your .env to use this.
        |
        | Required env vars:
        | - SMTP2GO_USERNAME: Your SMTP2GO SMTP username
        | - SMTP2GO_PASSWORD: Your SMTP2GO SMTP password
        |
        */

        'smtp2go' => [
            'transport' => 'smtp',
            'host' => env('SMTP2GO_HOST', 'mail.smtp2go.com'),
            'port' => env('SMTP2GO_PORT', 587),
            'username' => env('SMTP2GO_USERNAME'),
            'password' => env('SMTP2GO_PASSWORD'),
            'encryption' => env('SMTP2GO_ENCRYPTION', 'tls'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        /*
        |--------------------------------------------------------------------------
        | SendGrid Mailer
        |--------------------------------------------------------------------------
        |
        | SendGrid configuration for easy switching.
        | Set MAIL_MAILER=sendgrid in your .env to use this.
        |
        */

        'sendgrid' => [
            'transport' => 'smtp',
            'host' => env('SENDGRID_HOST', 'smtp.sendgrid.net'),
            'port' => env('SENDGRID_PORT', 587),
            'username' => env('SENDGRID_USERNAME', 'apikey'),
            'password' => env('SENDGRID_API_KEY'),
            'encryption' => env('SENDGRID_ENCRYPTION', 'tls'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        /*
        |--------------------------------------------------------------------------
        | Mailgun Mailer
        |--------------------------------------------------------------------------
        |
        | Mailgun configuration for easy switching.
        | Set MAIL_MAILER=mailgun in your .env to use this.
        |
        */

        'mailgun' => [
            'transport' => 'smtp',
            'host' => env('MAILGUN_HOST', 'smtp.mailgun.org'),
            'port' => env('MAILGUN_PORT', 587),
            'username' => env('MAILGUN_USERNAME'),
            'password' => env('MAILGUN_PASSWORD'),
            'encryption' => env('MAILGUN_ENCRYPTION', 'tls'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp2go',
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];
