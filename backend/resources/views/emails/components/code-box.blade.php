{{--
    Email Code/Token Box Component
    
    Usage: @include('emails.components.code-box', ['code' => '123456'])
    
    Optional:
    - $label: Text to show above the code box
    - $size: 'large' (default for short codes), 'medium', 'small' (for longer codes)
--}}

@php
    $codeLength = strlen($code ?? '');
    
    // Auto-detect size based on code length if not specified
    if (!isset($size)) {
        if ($codeLength <= 6) {
            $size = 'large';
        } elseif ($codeLength <= 12) {
            $size = 'medium';
        } else {
            $size = 'small';
        }
    }
    
    $styles = [
        'large' => 'font-size: 32px; letter-spacing: 8px; padding: 15px 30px;',
        'medium' => 'font-size: 24px; letter-spacing: 4px; padding: 12px 24px;',
        'small' => 'font-size: 16px; letter-spacing: 2px; padding: 10px 20px; word-break: break-all;',
    ];
    
    $style = $styles[$size] ?? $styles['large'];
@endphp

@if(isset($label))
<p style="margin: 0 0 15px 0; font-size: 14px; color: #8b8e9a; text-align: center;">
    {{ $label }}
</p>
@endif

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td align="center">
            <div style="display: inline-block; {{ $style }} background-color: #32353f; border-radius: 8px; font-weight: 700; color: #d4a853; font-family: 'JetBrains Mono', 'SF Mono', 'Courier New', monospace; max-width: 100%;">
                {{ $code }}
            </div>
        </td>
    </tr>
</table>

