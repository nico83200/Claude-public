@php
    $plain = fn ($t) => str_replace('**', '', (string) $t);
    $parts = [$mail['greeting'], $plain($mail['intro']), $plain($mail['message']), $mail['button'] ? $mail['button'].' : '.$mail['url'] : '', $plain($mail['outro']), $mail['signature'], $mail['footer']];
@endphp
{!! implode("\n\n", array_filter($parts, fn ($p) => trim((string) $p) !== '')) !!}
