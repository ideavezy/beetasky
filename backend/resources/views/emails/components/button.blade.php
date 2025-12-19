{{--
    Email Button Component
    
    Usage: @include('emails.components.button', ['url' => 'https://...', 'text' => 'Click Here'])
    
    Optional:
    - $style: 'primary' (default), 'secondary', 'outline'
--}}

@php
    $style = $style ?? 'primary';
    
    $styles = [
        'primary' => 'background-color: #d4a853; color: #1a1c24;',
        'secondary' => 'background-color: #4a4d5a; color: #f5f7ff;',
        'outline' => 'background-color: transparent; color: #d4a853; border: 2px solid #d4a853;',
    ];
    
    $buttonStyle = $styles[$style] ?? $styles['primary'];
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td align="center" style="padding: 10px 0;">
            <a href="{{ $url }}" 
               class="btn-{{ $style }}"
               style="display: inline-block; padding: 16px 40px; {{ $buttonStyle }} font-size: 16px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                {{ $text }}
            </a>
        </td>
    </tr>
</table>

