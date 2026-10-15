<x-layouts.app title="Messages · Admin">
    <x-page-header title="Messages aux utilisateurs" subtitle="Une information ou une offre ponctuelle, envoyée en notification à tous ou selon l'offre" />
    @include('admin._nav')
    <div class="grid gap-4 lg:grid-cols-5">
        <x-section title="Nouveau message" class="lg:col-span-3">
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-3" x-data="{ audience: @js(old('audience', 'all')) }"
                  data-confirm="Envoyer ce message maintenant ? Il ne pourra pas être retiré." data-confirm-ok="Envoyer" data-confirm-tone="neutral" data-announcement-form>
                @csrf
                <x-field name="title" label="Titre" required maxlength="120" placeholder="ex. -20 % sur l'offre annuelle jusqu'au 30 novembre" />
                <x-textarea name="body" label="Message" rows="4" required maxlength="1000" placeholder="Texte affiché dans la notification." />
                <x-field name="url" label="Lien à l'ouverture (facultatif)" maxlength="500" placeholder="/abonnement ou https://…" />
                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium text-slate-700">Destinataires</legend>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="audience" value="all" x-model="audience"> Tous les utilisateurs <span class="text-slate-500">({{ $counts['all'] }})</span></label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="audience" value="plans" x-model="audience"> Selon l'offre</label>
                    <div x-show="audience === 'plans'" x-cloak class="ml-6 space-y-1.5">
                        @foreach ($plans as $plan)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="plans[]" value="{{ $plan->id }}" @checked(in_array($plan->id, old('plans', $selected)))> {{ $plan->name }} <span class="text-slate-500">({{ $counts[$plan->id] ?? 0 }})</span>@if ($plan->archived_at)<x-badge color="slate">archivée</x-badge>@endif</label>
                        @endforeach
                        <p class="text-xs text-slate-500">Un espace sans offre payante en cours compte dans l'offre gratuite. Un utilisateur présent dans plusieurs espaces le reçoit une seule fois.</p>
                    </div>
                    @error('plans')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="with_email" value="1" class="mt-1" @checked(old('with_email'))> <span>Envoyer aussi par e-mail <span class="block text-xs text-slate-500">Uniquement aux utilisateurs qui acceptent les e-mails de notification.</span></span></label>
                <button class="btn-primary w-full"><x-icon name="bell" class="size-4" /> Envoyer</button>
            </form>
            <p class="mt-3 text-xs text-slate-500">Le message arrive dans les notifications de l'application (cloche) et, si l'utilisateur les a activées sur son téléphone, en notification. En cas de doute : <a href="{{ route('admin.notifications.index') }}" class="underline">Administration › Notifications</a>.</p>
        </x-section>
        <x-section title="Messages envoyés" class="lg:col-span-2">
            @forelse ($history as $a)
                <div class="border-t border-slate-100 py-3 text-sm first:border-0" data-announcement>
                    <p class="font-medium text-slate-900">{{ $a->title }}</p>
                    <p class="text-slate-600">{{ \Illuminate\Support\Str::limit($a->body, 140) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $a->created_at->format('d/m/Y H:i') }} · {{ $a->plan_ids ? $plans->whereIn('id', $a->plan_ids)->pluck('name')->implode(', ') : 'Tous' }} · {{ $a->recipients_count }} destinataire(s) · {{ $a->devices_reached }} appareil(s) atteint(s){{ $a->with_email ? ' · e-mail' : '' }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucun message envoyé.</p>
            @endforelse
        </x-section>
    </div>
</x-layouts.app>
