@extends('emails.layouts.base')

@section('title', "You're Invited - " . $companyName)

@section('preheader')
You've been invited to join {{ $companyName }}'s client portal.
@endsection

@section('content')
    @include('emails.components.heading', ['text' => "You've Been Invited!"])
    
    @include('emails.components.paragraph', [
        'text' => "Hello <strong style=\"color: #f5f7ff;\">{$recipientName}</strong>,"
    ])
    
    @include('emails.components.paragraph', [
        'text' => "You've been invited to join <strong style=\"color: #f5f7ff;\">{$companyName}</strong>'s client portal. Click the button below to accept the invitation and create your account."
    ])
    
    <div style="padding: 10px 0 20px 0;">
        @include('emails.components.button', [
            'url' => $confirmationUrl,
            'text' => 'Accept Invitation'
        ])
    </div>
    
    @if($token)
        @include('emails.components.code-box', [
            'code' => $token,
            'label' => 'Or use this verification code:'
        ])
    @endif
    
    <div style="margin-top: 30px;">
        @include('emails.components.paragraph', [
            'text' => "If you weren't expecting this invitation, you can safely ignore this email.",
            'muted' => true,
            'small' => true
        ])
    </div>
@endsection

@section('footer')
    <p style="margin: 0; font-size: 12px; color: #6b6e7a; text-align: center;">
        © {{ date('Y') }} {{ $companyName }} • This is an automated message, please do not reply.
    </p>
@endsection
