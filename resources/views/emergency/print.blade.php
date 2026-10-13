<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Fiche d'urgence · {{ $horse->shortName() }}</title>
    <style>
        @page { size: A5 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #141915; background: #f4f1ea; font-size: 10.5pt; line-height: 1.35; }
        .sheet { width: 148mm; min-height: 210mm; margin: 12px auto; padding: 9mm; background: #fff; border-radius: 5mm; display: flex; flex-direction: column; gap: 4mm; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 4mm; border-bottom: 2px solid #b91c1c; padding-bottom: 3mm; }
        .tag { display: inline-block; background: #b91c1c; color: #fff; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; font-size: 9pt; padding: 1mm 4mm; border-radius: 99px; }
        h1 { font-size: 22pt; margin: 1.5mm 0 0; line-height: 1.1; }
        .official { color: #64748b; font-size: 9pt; }
        .call { border: 2px solid #b91c1c; border-radius: 3mm; padding: 3mm 4mm; }
        .call .label { font-size: 8.5pt; color: #b91c1c; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
        .call .who { font-size: 13pt; font-weight: 700; }
        .call .tel { font-size: 16pt; font-weight: 800; letter-spacing: .02em; }
        h2 { font-size: 9pt; text-transform: uppercase; letter-spacing: .06em; color: #475569; margin: 0 0 1.5mm; }
        .row { display: flex; justify-content: space-between; gap: 3mm; padding: 1mm 0; border-top: 1px solid #e5e7eb; }
        .row:first-of-type { border-top: 0; }
        .row b { font-weight: 600; }
        .note { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 2mm 3mm; white-space: pre-line; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1mm 4mm; }
        .grid span { color: #64748b; font-size: 8.5pt; display: block; }
        .muted { color: #64748b; }
        footer { margin-top: auto; display: flex; align-items: center; gap: 4mm; border-top: 1px solid #e5e7eb; padding-top: 3mm; }
        .qr svg { width: 34mm; height: 34mm; display: block; }
        footer p { margin: 0; font-size: 9pt; color: #334155; }
        footer strong { color: #141915; }
        .actions { text-align: center; margin: 16px; }
        .actions button { font: inherit; padding: 10px 22px; border-radius: 99px; border: 0; background: #174d37; color: #fff; font-weight: 600; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { margin: 0; border-radius: 0; width: auto; min-height: 194mm; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    @php $s = $sheet; @endphp
    <div class="actions"><button type="button" onclick="window.print()">Imprimer</button></div>
    <div class="sheet" data-emergency-print>
        <header>
            <div>
                <span class="tag">Fiche d'urgence</span>
                <h1>{{ $horse->shortName() }}</h1>
                @if ($horse->usual_name)<div class="official">{{ $horse->official_name }}</div>@endif
            </div>
        </header>

        @if ($s['contact']['phone'] || $s['contact']['name'])
            <div class="call" data-print-contact>
                <div class="label">En cas d'urgence, appeler</div>
                <div class="who">{{ $s['contact']['name'] }}</div>
                @if ($s['contact']['phone'])<div class="tel">{{ $s['contact']['phone'] }}</div>@endif
            </div>
        @endif

        @if ($s['professionals']->isNotEmpty())
            <section>
                <h2>Intervenants habituels</h2>
                @foreach ($s['professionals'] as $p)
                    <div class="row"><span><b>{{ $p->fullName() }}</b> · {{ $p->kindLabel() }}</span><b>{{ $p->phone }}</b></div>
                @endforeach
            </section>
        @endif

        @if ($s['notes'] || $s['precautions'])
            <section>
                <h2>À savoir</h2>
                <div class="note">{{ trim(($s['notes'] ?? '')."\n".($s['precautions'] ?? '')) }}</div>
            </section>
        @endif

        <section>
            <h2>Traitements en cours</h2>
            @forelse ($s['treatments'] as $t)
                <div class="row"><span><b>{{ $t->product }}</b>{{ $t->dosage ? ' — '.$t->dosage : '' }}{{ $t->frequency ? ' · '.$t->frequency : '' }}</span><span class="muted">{{ $t->ends_on ? 'jusqu\'au '.$t->ends_on->format('d/m/Y') : '' }}</span></div>
            @empty
                <div class="muted">Aucun traitement en cours au {{ now()->format('d/m/Y') }}.</div>
            @endforelse
        </section>

        @if ($s['identity'])
            <section>
                <h2>Identification</h2>
                <div class="grid">
                    @foreach ($s['identity'] as $label => $value)<div><span>{{ $label }}</span>{{ $value }}</div>@endforeach
                </div>
            </section>
        @endif

        <footer>
            <div class="qr">{!! preg_replace('/<\?xml[^>]*\?>/', '', $qr) !!}</div>
            <p data-print-freshness><strong>Informations au {{ now()->format('d/m/Y') }}.</strong><br>
                Scannez le QR code pour la version toujours à jour : traitements, contacts et précautions y sont actualisés en direct par le propriétaire.<br>
                <span class="muted">Aucun compte nécessaire · Jack&amp;Cie</span></p>
        </footer>
    </div>
</body>
</html>
