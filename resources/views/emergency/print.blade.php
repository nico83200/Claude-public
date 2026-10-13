<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>QR code d'urgence · {{ $horse->shortName() }}</title>
    <style>
        @page { size: A6 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #141915; background: #f4f1ea; }
        .sheet { width: 105mm; min-height: 148mm; margin: 12px auto; padding: 8mm; background: #fff; border-radius: 6mm; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: space-between; }
        .tag { display: inline-block; background: #b91c1c; color: #fff; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; font-size: 11pt; padding: 2mm 5mm; border-radius: 99px; }
        h1 { font-size: 24pt; margin: 4mm 0 1mm; }
        .qr svg { width: 62mm; height: 62mm; display: block; }
        p { margin: 0; font-size: 10pt; color: #475569; }
        .actions { text-align: center; margin: 16px; }
        .actions button { font: inherit; padding: 10px 22px; border-radius: 99px; border: 0; background: #174d37; color: #fff; font-weight: 600; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { margin: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimer</button></div>
    <div class="sheet" data-emergency-print>
        <div>
            <span class="tag">Urgence</span>
            <h1>{{ $horse->shortName() }}</h1>
            <p>Scannez pour les contacts, traitements en cours et précautions</p>
        </div>
        <div class="qr">{!! preg_replace('/<\?xml[^>]*\?>/', '', $qr) !!}</div>
        <p>Jack&amp;Cie · aucune connexion nécessaire</p>
    </div>
</body>
</html>
