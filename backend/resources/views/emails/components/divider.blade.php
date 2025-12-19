{{--
    Email Divider Component
    
    Usage: @include('emails.components.divider')
    
    Optional:
    - $spacing: 'sm', 'md' (default), 'lg'
--}}

@php
    $spacing = $spacing ?? 'md';
    
    $paddings = [
        'sm' => '10px 0',
        'md' => '20px 0',
        'lg' => '30px 0',
    ];
    
    $padding = $paddings[$spacing] ?? $paddings['md'];
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="padding: {{ $padding }};">
            <div style="height: 1px; background-color: #4a4d5a;"></div>
        </td>
    </tr>
</table>

