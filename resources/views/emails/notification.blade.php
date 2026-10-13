@php
    $c = $mail['colors'];
    // Texte saisi : échappé, **gras** autorisé, lignes vides = paragraphes, retours à la ligne conservés.
    $paras = function (?string $text) use ($c) {
        if (trim((string) $text) === '') return '';
        return collect(preg_split("/\n\s*\n/", trim($text)))->map(function ($p) use ($c) {
            $html = nl2br(e(trim($p)), false);
            $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
            return '<p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:'.e($c['text_color']).'">'.$html.'</p>';
        })->implode('');
    };
    $align = $mail['align'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $mail['subject'] }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $c['background_color'] }};font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-text-size-adjust:100%">
<div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ \Illuminate\Support\Str::limit($mail['message'], 120) }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $c['background_color'] }}">
<tr><td align="center" style="padding:24px 12px">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px">
        @if ($mail['logo'])
        <tr><td align="{{ $align }}" style="padding:8px 8px 20px" data-mail-logo>
            @if ($mail['logo_link'])<a href="{{ url('/') }}" style="text-decoration:none">@endif
            <img src="{{ $mail['logo'] }}" width="{{ $mail['logo_width'] }}" alt="{{ $mail['app'] }}" style="display:inline-block;width:{{ $mail['logo_width'] }}px;max-width:100%;height:auto;border:0">
            @if ($mail['logo_link'])</a>@endif
        </td></tr>
        @endif
        <tr><td style="background:{{ $c['card_color'] }};border-radius:14px;border-top:4px solid {{ $c['accent_color'] }};padding:32px 28px">
            @if ($mail['greeting'] !== '')
                <p style="margin:0 0 18px;font-size:19px;font-weight:700;color:{{ $c['text_color'] }}">{{ $mail['greeting'] }}</p>
            @endif
            <div data-mail-intro>{!! $paras($mail['intro']) !!}</div>
            <div data-mail-message>{!! $paras($mail['message']) !!}</div>
            @if ($mail['button'])
                <table role="presentation" cellpadding="0" cellspacing="0" align="{{ $align }}" style="margin:8px {{ $align === 'center' ? 'auto' : '0' }} 24px"><tr>
                    <td style="border-radius:10px;background:{{ $c['button_color'] }}">
                        <a href="{{ $mail['url'] }}" style="display:inline-block;padding:13px 28px;font-size:16px;font-weight:600;color:{{ $c['button_text_color'] }};text-decoration:none;border-radius:10px">{{ $mail['button'] }}</a>
                    </td>
                </tr></table>
                <div style="clear:both"></div>
            @endif
            <div data-mail-outro>{!! $paras($mail['outro']) !!}</div>
            @if ($mail['signature'] !== '')
                <p style="margin:8px 0 0;font-size:16px;line-height:1.6;color:{{ $c['text_color'] }}">{!! nl2br(e($mail['signature']), false) !!}</p>
            @endif
            @if ($mail['fallback'])
                <p style="margin:24px 0 0;padding-top:16px;border-top:1px solid #e5e7eb;font-size:13px;line-height:1.5;color:#6b7280">{{ $mail['fallback'] }}<br><a href="{{ $mail['url'] }}" style="color:{{ $c['button_color'] }};word-break:break-all">{{ $mail['url'] }}</a></p>
            @endif
        </td></tr>
        @if ($mail['footer'] !== '')
        <tr><td align="center" style="padding:20px 16px 4px;font-size:12px;line-height:1.5;color:#6b7280" data-mail-footer>{!! nl2br(e($mail['footer']), false) !!}</td></tr>
        @endif
    </table>
</td></tr>
</table>
</body>
</html>
