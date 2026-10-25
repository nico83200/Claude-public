{{-- Message d'accompagnement : rassurer sans minimiser, et donner les bons réflexes (aucun avis médical). --}}
<div class="rounded-3xl bg-brand-50 p-5 ring-1 ring-brand-200" data-attention-calm>
    <p class="text-[17px] font-semibold text-brand-900">Gardez votre calme, vous faites ce qu'il faut.</p>
    <p class="mt-1 text-sm text-brand-900">Vous avez remarqué quelque chose d'inhabituel et vous le notez : c'est le bon réflexe.</p>
    <ul class="mt-3 space-y-1.5 text-sm text-slate-700">
        <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-700" /> Laissez votre cheval au calme et restez près de lui.</li>
        <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-700" /> Notez ce que vous voyez : l'heure, la durée, ce qui change. Une photo aide beaucoup.</li>
        <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-700" /> Au moindre doute, appelez votre vétérinaire : il saura vous dire quoi faire et s'il doit passer.</li>
        @if (($severity ?? null) === 'alert')
            <li class="flex gap-2"><x-icon name="phone" class="mt-0.5 size-4 shrink-0 text-brand-700" /> Si cela s'intensifie, ne s'arrête pas ou si votre cheval change de comportement, appelez-le sans attendre.</li>
        @endif
    </ul>
</div>
