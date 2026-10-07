<?php
declare(strict_types=1);

/**
 * Lecture de fichiers tableur sans dépendance : CSV, XLSX, ODS, et les « .xls » exportés
 * au format HTML ou XML 2003 (fréquent sur les sites fournisseurs).
 * Renvoie la première feuille sous forme de lignes de cellules texte.
 */
function spreadsheet_read(string $path, string $originalName, int $maxRows = 5000): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $head = (string)file_get_contents($path, false, null, 0, 2048);
    $rows = match (true) {
        str_starts_with($head, "\xD0\xCF\x11\xE0") => throw new RuntimeException(
            'Ce fichier est au format Excel 97-2003 (.xls binaire). Ouvrez-le dans Excel et enregistrez-le en « Classeur Excel (.xlsx) » ou en CSV, puis importez-le à nouveau.'),
        str_starts_with($head, 'PK') => in_array($ext, ['ods'], true) ? sheet_read_ods($path) : sheet_read_xlsx($path),
        (bool)preg_match('/<\?xml[^>]*>\s*(<\?mso[^>]*>\s*)?<Workbook/i', $head) || stripos($head, 'urn:schemas-microsoft-com:office:spreadsheet') !== false => sheet_read_xml2003($path),
        (bool)preg_match('/<(html|table)\b/i', $head) => sheet_read_html($path),
        default => sheet_read_csv($path),
    };
    // Lignes entièrement vides retirées, cellules nettoyées, largeur homogène
    $out = [];
    foreach ($rows as $r) {
        $r = array_map(fn($v) => trim(preg_replace('/[\x{00A0}\s]+/u', ' ', (string)$v) ?? ''), $r);
        if (implode('', $r) === '') {
            continue;
        }
        $out[] = $r;
        if (count($out) >= $maxRows + 50) {
            break;
        }
    }
    $width = $out ? max(array_map('count', $out)) : 0;
    foreach ($out as &$r) {
        $r = array_pad($r, $width, '');
    }
    return $out;
}

function sheet_to_utf8(string $s): string
{
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
    if (str_starts_with($s, "\xFF\xFE") || str_starts_with($s, "\xFE\xFF")) {
        return mb_convert_encoding(substr($s, 2), 'UTF-8', str_starts_with($s, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
    }
    return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
}

function sheet_read_csv(string $path): array
{
    $text = sheet_to_utf8((string)file_get_contents($path));
    $sample = implode("\n", array_slice(preg_split('/\R/', $text) ?: [], 0, 10));
    $sep = ';';
    $best = -1;
    foreach ([';', ',', "\t", '|'] as $c) {
        $n = substr_count($sample, $c);
        if ($n > $best) {
            [$best, $sep] = [$n, $c];
        }
    }
    $fh = fopen('php://temp', 'w+');
    fwrite($fh, $text);
    rewind($fh);
    $rows = [];
    while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
        $rows[] = $r;
    }
    fclose($fh);
    return $rows;
}

/** Index de colonne depuis une référence de cellule Excel (« C12 » → 2). */
function sheet_col_index(string $ref): int
{
    $letters = preg_replace('/\d+/', '', strtoupper($ref)) ?? '';
    $n = 0;
    foreach (str_split($letters) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return max(0, $n - 1);
}

function sheet_read_xlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Fichier Excel illisible.');
    }
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml);
        foreach ($sx->si ?? [] as $si) {
            // Texte simple ou texte enrichi (plusieurs <r><t>)
            $t = isset($si->t) ? (string)$si->t : '';
            foreach ($si->r ?? [] as $r) {
                $t .= (string)$r->t;
            }
            $shared[] = $t;
        }
    }
    // Première feuille du classeur
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb !== false && $rels !== false) {
        $wbx = simplexml_load_string($wb);
        $first = $wbx->sheets->sheet[0] ?? null;
        if ($first) {
            $rid = (string)$first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach (simplexml_load_string($rels)->Relationship as $rel) {
                if ((string)$rel['Id'] === $rid) {
                    $target = ltrim((string)$rel['Target'], '/');
                    $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }
    }
    $xml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($xml === false) {
        throw new RuntimeException('Aucune feuille trouvée dans le classeur Excel.');
    }
    $sx = simplexml_load_string($xml);
    $rows = [];
    foreach ($sx->sheetData->row ?? [] as $row) {
        $r = [];
        $i = 0;
        foreach ($row->c as $c) {
            $i = isset($c['r']) ? sheet_col_index((string)$c['r']) : $i;
            $type = (string)$c['t'];
            $v = match ($type) {
                's' => $shared[(int)$c->v] ?? '',
                'inlineStr' => (string)($c->is->t ?? ''),
                'b' => ((string)$c->v) === '1' ? 'VRAI' : 'FAUX',
                default => (string)$c->v,
            };
            // Nombres : on évite les artefacts binaires (12.300000000000001)
            if ($type === '' && is_numeric($v) && str_contains($v, '.')) {
                $v = rtrim(rtrim(sprintf('%.10F', (float)$v), '0'), '.');
            }
            $r[$i] = $v;
            $i++;
        }
        if ($r) {
            $line = array_fill(0, max(array_keys($r)) + 1, '');
            foreach ($r as $k => $v) {
                $line[$k] = $v;
            }
            $rows[] = $line;
        }
    }
    return $rows;
}

function sheet_read_ods(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true || ($xml = $zip->getFromName('content.xml')) === false) {
        throw new RuntimeException('Fichier OpenDocument illisible.');
    }
    $zip->close();
    $doc = new DOMDocument();
    $doc->loadXML($xml, LIBXML_NONET);
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
    $rows = [];
    $table = $xp->query('//table:table')->item(0);
    if (!$table) {
        return [];
    }
    foreach ($xp->query('.//table:table-row', $table) as $tr) {
        $r = [];
        foreach ($xp->query('table:table-cell|table:covered-table-cell', $tr) as $td) {
            $rep = min(50, max(1, (int)$td->getAttribute('table:number-columns-repeated')));
            $v = $td->getAttribute('office:value') !== '' && $td->getAttribute('office:value-type') !== 'string'
                ? $td->getAttribute('office:value') : trim($td->textContent);
            for ($k = 0; $k < $rep; $k++) {
                $r[] = $v;
            }
        }
        $rows[] = $r;
    }
    return $rows;
}

function sheet_read_xml2003(string $path): array
{
    $doc = new DOMDocument();
    @$doc->loadXML(sheet_to_utf8((string)file_get_contents($path)), LIBXML_NONET);
    $rows = [];
    $ws = $doc->getElementsByTagName('Worksheet')->item(0);
    if (!$ws) {
        return [];
    }
    foreach ($ws->getElementsByTagName('Row') as $tr) {
        $r = [];
        foreach ($tr->getElementsByTagName('Cell') as $td) {
            $idx = $td->getAttribute('ss:Index');
            if ($idx !== '') {
                $r = array_pad($r, (int)$idx - 1, '');
            }
            $r[] = trim($td->textContent);
        }
        $rows[] = $r;
    }
    return $rows;
}

function sheet_read_html(string $path): array
{
    $html = sheet_to_utf8((string)file_get_contents($path));
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    $rows = [];
    $table = $doc->getElementsByTagName('table')->item(0);
    if (!$table) {
        return [];
    }
    foreach ($table->getElementsByTagName('tr') as $tr) {
        $r = [];
        foreach ($tr->childNodes as $td) {
            if ($td instanceof DOMElement && in_array(strtolower($td->tagName), ['td', 'th'], true)) {
                $r[] = trim($td->textContent);
            }
        }
        $rows[] = $r;
    }
    return $rows;
}

/**
 * Repère la ligne d'en-têtes : la première ligne, parmi les 15 premières, dont la plupart
 * des cellules sont du texte (les fichiers fournisseurs commencent souvent par un titre).
 */
function sheet_header_row(array $rows): int
{
    $best = 0;
    $bestScore = -1;
    foreach (array_slice($rows, 0, 15, true) as $i => $r) {
        $filled = array_filter($r, fn($v) => $v !== '');
        $text = array_filter($filled, fn($v) => !is_numeric(str_replace([',', ' ', '€'], ['.', '', ''], $v)) && mb_strlen($v) <= 60);
        $score = count($text) * 2 - (count($filled) - count($text));
        if (count($filled) >= 2 && $score > $bestScore + 1) {
            [$best, $bestScore] = [$i, $score];
        }
    }
    return $best;
}

/** Nombre au format français ou anglais : « 1 234,56 € », « 1,234.56 », « 12.5 ». */
function parse_number(?string $v): ?float
{
    $v = trim((string)$v);
    if ($v === '') {
        return null;
    }
    $v = preg_replace('/[^\d,.\-]/u', '', $v) ?? '';
    if ($v === '' || $v === '-') {
        return null;
    }
    $lastComma = strrpos($v, ',');
    $lastDot = strrpos($v, '.');
    if ($lastComma !== false && $lastDot !== false) {
        // Le dernier séparateur est le séparateur décimal
        $v = $lastComma > $lastDot ? str_replace(['.', ','], ['', '.'], $v) : str_replace(',', '', $v);
    } elseif ($lastComma !== false) {
        $v = substr_count($v, ',') > 1 ? str_replace(',', '', $v) : str_replace(',', '.', $v);
    } elseif (substr_count($v, '.') > 1) {
        $v = str_replace('.', '', $v);
    }
    return is_numeric($v) ? (float)$v : null;
}
