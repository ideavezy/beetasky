{{--
    Email Paragraph Component
    
    Usage: @include('emails.components.paragraph', ['text' => 'Your message here'])
    
    Optional:
    - $align: 'center' (default), 'left', 'right'
    - $muted: false (default), true - for secondary/muted text
    - $small: false (default), true - for smaller text
--}}

@php
    $align = $align ?? 'center';
    $muted = $muted ?? false;
    $small = $small ?? false;
    
    $color = $muted ? '#6b6e7a' : '#c5c8d4';
    $fontSize = $small ? '14px' : '16px';
@endphp

<p style="margin: 0 0 16px 0; font-size: {{ $fontSize }}; line-height: 1.6; color: {{ $color }}; text-align: {{ $align }};">
    {!! $text !!}
</p>

