{{--
    Email Heading Component
    
    Usage: @include('emails.components.heading', ['text' => 'Welcome!'])
    
    Optional:
    - $level: 1 (default), 2, 3
    - $align: 'center' (default), 'left', 'right'
--}}

@php
    $level = $level ?? 1;
    $align = $align ?? 'center';
    
    $sizes = [
        1 => 'font-size: 28px; margin: 0 0 20px 0;',
        2 => 'font-size: 22px; margin: 0 0 16px 0;',
        3 => 'font-size: 18px; margin: 0 0 12px 0;',
    ];
    
    $fontSize = $sizes[$level] ?? $sizes[1];
@endphp

<h{{ $level }} style="{{ $fontSize }} font-weight: 600; color: #f5f7ff; text-align: {{ $align }};">
    {{ $text }}
</h{{ $level }}>

