<x-layouts.app title="Vaccins concours · Admin">
    <x-page-header title="Vaccins concours" subtitle="Règles appliquées pour vérifier, d'après le carnet de santé, que le cheval peut concourir" />
    @include('admin._nav')
    @php
        $vaccineNames = \App\Support\Vaccines::TYPES;
        $window = fn ($m, $d) => \App\Support\VaccineRules::window((int) $m, (int) $d);
    @endphp
    <form method="POST" action="{{ route('admin.vaccine-rules.update') }}" class="space-y-4" data-vaccine-rules-form>
        @csrf @method('PUT')
        <x-section title="Général">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="default" label="Règlement proposé par défaut" :options="collect($config['sets'])->map(fn ($s) => $s['label'])->all()" :value="$config['default']"
                    help="Proposé pour un nouveau concours (ensuite, celui du dernier concours saisi) et appliqué aux concours sans règlement choisi." />
                <x-field name="reminder_days" label="Rappels avant le concours (jours)" :value="implode(', ', $config['reminder_days'])" placeholder="21, 10"
                    help="Notification aux personnes qui voient la santé du cheval quand ses vaccins sont à revoir. Vide : aucun rappel." />
            </div>
            <p class="mt-3 text-xs text-slate-500">Les règlements évoluent : vérifiez les textes officiels (FFE : règlement des compétitions, dispositions générales ; FEI : Veterinary Regulations) et mettez à jour la date de vérification.</p>
        </x-section>

        @foreach ($config['sets'] as $key => $set)
            @php $default = $defaults['sets'][$key]; @endphp
            <x-section :title="$set['label']" :data-vaccine-set="$key">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field :name="'sets['.$key.'][label]'" label="Nom affiché" :value="$set['label']" required maxlength="80" />
                    <x-field :name="'sets['.$key.'][short]'" label="Nom court (pastilles, notifications)" :value="$set['short']" required maxlength="30" />
                    <x-checkbox :name="'sets['.$key.'][active]'" label="Proposé pour les concours" :checked="$set['active']" />
                    <x-checkbox :name="'sets['.$key.'][arrival]'" label="Délais comptés depuis l'arrivée sur le concours" :checked="$set['arrival']" help="FEI : arrivée sur le site ; FFE : jour du concours." />
                    <x-textarea :name="'sets['.$key.'][source]'" label="Texte de référence" :value="$set['source']" rows="2" class="sm:col-span-2" />
                    <x-textarea :name="'sets['.$key.'][note]'" label="Précision affichée sous les règles (facultatif)" :value="$set['note'] ?? ''" rows="2" class="sm:col-span-2" />
                    <x-field :name="'sets['.$key.'][checked_on]'" type="date" label="Vérifié le" :value="$set['checked_on']" required />
                </div>
                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @foreach (\App\Support\VaccineRules::VACCINES as $vaccine)
                        @php
                            $dose = $set['vaccines'][$vaccine];
                            $ref = $default['vaccines'][$vaccine];
                            $base = 'sets['.$key.'][vaccines]['.$vaccine.']';
                        @endphp
                        <div class="rounded-2xl bg-slate-50 p-4" data-vaccine-dose="{{ $key }}-{{ $vaccine }}">
                            <x-checkbox :name="$base.'[required]'" :label="$vaccineNames[$vaccine].' exigée'" :checked="$dose['required']" :help="'Valeur vérifiée : '.($ref['required'] ? 'exigée' : 'non exigée').'.'" />
                            <div class="mt-2 grid grid-cols-2 gap-3">
                                <x-field :name="$base.'[months]'" type="number" min="0" max="36" label="Rappel valable (mois)" :value="$dose['months']" />
                                <x-field :name="$base.'[days]'" type="number" min="0" max="400" label="+ jours" :value="$dose['days']" />
                                <x-field :name="$base.'[primo_months]'" type="number" min="0" max="36" label="Après la primo (mois)" :value="$dose['primo_months']" />
                                <x-field :name="$base.'[primo_days]'" type="number" min="0" max="400" label="+ jours" :value="$dose['primo_days']" />
                                <x-field :name="$base.'[min_days]'" type="number" min="0" max="60" label="Jours minimum avant le concours" :value="$dose['min_days']" class="col-span-2" />
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Valeurs vérifiées : rappel {{ $window($ref['months'], $ref['days']) }}, après la 2e injection de la primo-vaccination {{ $window($ref['primo_months'], $ref['primo_days']) }}, au moins {{ $ref['min_days'] }} jours avant.</p>
                        </div>
                    @endforeach
                </div>
            </x-section>
        @endforeach
        <button class="btn-primary">Enregistrer</button>
    </form>
    <form method="POST" action="{{ route('admin.vaccine-rules.reset') }}" class="mt-4" data-vaccine-rules-reset>
        @csrf
        <button class="btn-ghost text-sm">Rétablir les valeurs vérifiées le {{ \Illuminate\Support\Carbon::parse($defaults['sets']['ffe_amateur']['checked_on'])->format('d/m/Y') }}</button>
    </form>
</x-layouts.app>
