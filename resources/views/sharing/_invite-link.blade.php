@if ($link = session('invite_link'))
        {{-- L'e-mail part toujours ; le même lien peut être envoyé par message (il ne s'ouvre que pour l'adresse invitée). --}}
        @php $msg = ($inviteIntro ?? 'Je te partage le dossier de '.$inviteHorse->shortName().' sur Jack&Cie.').' Crée ton compte ou connecte-toi avec '.$link['email'].' : '.$link['url']; @endphp
        <div class="card mb-4 p-4" data-invite-link x-data="{ copied: false }">
            <p class="font-semibold">Prévenez aussi la personne par message</p>
            <p class="mt-1 text-sm text-slate-600">L'invitation est partie à {{ $link['email'] }}. Les e-mails arrivent parfois dans les indésirables : envoyez aussi le lien. Il ne fonctionne qu'avec cette adresse.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                <a href="https://wa.me/?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener" class="btn-primary min-h-11 justify-center" data-invite-whatsapp><x-icon name="send" class="size-4" /> WhatsApp</a>
                <a href="sms:?&body={{ rawurlencode($msg) }}" class="btn-secondary min-h-11 justify-center" data-invite-sms><x-icon name="sms" class="size-4" /> SMS</a>
                <button type="button" class="btn-secondary min-h-11 justify-center" @click="navigator.clipboard?.writeText(@js($link['url'])).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"><x-icon name="doc" class="size-4" /> <span x-text="copied ? 'Lien copié' : 'Copier le lien'">Copier le lien</span></button>
            </div>
        </div>
@endif
