<x-mail::message>
# Les emails fonctionnent

Ce message confirme que les réglages d'envoi de {{ $appName }} sont corrects.
Les rappels, invitations et notifications de facturation partiront avec cette configuration.

Envoyé le {{ now()->format('d/m/Y à H:i') }}.
</x-mail::message>
