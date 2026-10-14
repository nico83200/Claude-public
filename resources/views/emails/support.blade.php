Message envoyé depuis Assistance ({{ config('app.name') }})

De : {{ $sender->name }} <{{ $sender->email }}>
Sujet : {{ $topic }}

{{ $body }}

---
@foreach ($context as $label => $value)
{{ $label }} : {{ $value }}
@endforeach

Répondez directement à cet e-mail pour écrire à {{ $sender->name }}.
