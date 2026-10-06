<?php
declare(strict_types=1);

/**
 * Générateur PDF minimal (polices standard Helvetica, encodage WinAnsi),
 * suffisant pour les bons de commande, sans dépendance externe.
 */
final class SimplePdf
{
    private array $pages = [];
    private string $cur = '';
    private const K = 2.834645669; // mm -> points
    private const H = 297.0;        // hauteur A4 en mm
    private const W = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191, '(' => 333, ')' => 333,
        '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278, ':' => 278, ';' => 278, '<' => 584, '=' => 584,
        '>' => 584, '?' => 556, '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
        'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667, 'Q' => 778,
        'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278,
        '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333, 'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556,
        'f' => 278, 'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556,
        'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
        'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
    ];

    public function addPage(): void
    {
        if ($this->cur !== '' || $this->pages) {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
    }

    private static function enc(string $s): string
    {
        $s = str_replace(["\u{202F}", "\u{00A0}", '’', '—', '–', '…', '«', '»'], [' ', ' ', "'", '-', '-', '...', '"', '"'], $s);
        $s = (string)@iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $s);
    }

    public function textWidth(string $s, float $size, bool $bold = false): float
    {
        $w = 0;
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $w += self::W[$ch] ?? (ctype_digit($ch) ? 556 : 556);
        }
        return $w * $size / 1000 / self::K * ($bold ? 1.06 : 1);
    }

    private static function rgb(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', $c[0] / 255, $c[1] / 255, $c[2] / 255);
    }

    /** Texte ; $y = ligne de base, en mm depuis le haut. $align : L, R ou C par rapport à $x (et $w). */
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $color = [30, 35, 53], string $align = 'L', float $w = 0): void
    {
        $tw = $this->textWidth($s, $size, $bold);
        if ($align === 'R') {
            $x = $x + $w - $tw;
        } elseif ($align === 'C') {
            $x = $x + ($w - $tw) / 2;
        }
        $this->cur .= sprintf("BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            self::rgb($color), $bold ? 'F2' : 'F1', $size, $x * self::K, (self::H - $y) * self::K, self::enc($s));
    }

    public function rect(float $x, float $y, float $w, float $h, array $fill): void
    {
        $this->cur .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", self::rgb($fill), $x * self::K, (self::H - $y - $h) * self::K, $w * self::K, $h * self::K);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $color = [220, 224, 235], float $width = 0.3): void
    {
        $this->cur .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", self::rgb($color), $width * self::K,
            $x1 * self::K, (self::H - $y1) * self::K, $x2 * self::K, (self::H - $y2) * self::K);
    }

    /** Découpe un texte en lignes tenant dans $w mm. */
    public function wrap(string $s, float $w, float $size, bool $bold = false): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($s)) as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($try, $size, $bold) <= $w || $line === '') {
                $line = $try;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines ?: [''];
    }

    public function output(): string
    {
        $pages = $this->pages;
        $pages[] = $this->cur;
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $n = 5;
        foreach ($pages as $content) {
            $pageId = $n++;
            $contentId = $n++;
            $kids[] = "$pageId 0 R";
            $objs[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', $contentId);
            $stream = gzcompress($content);
            $objs[$contentId] = '<< /Filter /FlateDecode /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref' . "\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        $out .= 'trailer << /Size ' . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $out;
    }
}

/**
 * Bon(s) de commande en PDF. Plusieurs bons d'un même fournisseur (commande groupée)
 * produisent un document unique avec une section par adresse de livraison.
 */
function po_pdf(array $poIds): string
{
    $pos = [];
    foreach ($poIds as $id) {
        $po = one('SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.phone AS supplier_phone, s.contact_name, s.customer_number,
                          c.name AS center_name, c.address AS center_address, c.city AS center_city, c.phone AS center_phone, c.delivery_info
                   FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id WHERE po.id = ?', [$id]);
        if ($po) {
            $po['lines'] = all('SELECT * FROM purchase_order_lines WHERE purchase_order_id = ? ORDER BY label', [$id]);
            $pos[] = $po;
        }
    }
    if (!$pos) {
        throw new RuntimeException('Bon introuvable.');
    }
    $first = $pos[0];
    $grouped = count($pos) > 1;
    $pdf = new SimplePdf();
    $pdf->addPage();
    $violet = [99, 102, 241];
    $muted = [107, 114, 144];
    $y = 20.0;

    $header = function () use ($pdf, $first, $grouped, $violet, $muted, &$y) {
        $pdf->rect(0, 0, 210, 6, $violet);
        $pdf->text(15, 20, $grouped ? 'COMMANDE GROUPÉE' : 'BON DE COMMANDE', 9, true, $muted);
        $pdf->text(15, 30, $grouped ? (string)$first['group_ref'] : (string)$first['po_number'], 22, true, $violet);
        $pdf->text(15, 37, 'Date : ' . date_fr($first['ordered_at'] ?: $first['created_at']), 10, false, $muted);
        $company = setting('company_name') ?: app_name();
        $pdf->text(110, 20, $company, 12, true, [30, 35, 53], 'R', 85);
        $yy = 26;
        foreach (array_slice(preg_split('/\R/', (string)setting('company_address', '')), 0, 4) as $l) {
            $pdf->text(110, $yy, trim($l), 9, false, $muted, 'R', 85);
            $yy += 4.5;
        }
        $pdf->line(15, 44, 195, 44, $violet, 0.8);
        $y = 52;
    };
    $header();

    // Fournisseur
    $pdf->text(15, $y, 'FOURNISSEUR', 8, true, $muted);
    $pdf->text(15, $y + 6, (string)$first['supplier_name'], 12, true);
    $yy = $y + 11;
    foreach (array_filter([$first['contact_name'], trim(($first['supplier_email'] ?? '') . '  ' . ($first['supplier_phone'] ?? '')),
                 $first['customer_number'] ? 'N° client : ' . $first['customer_number'] : null]) as $l) {
        $pdf->text(15, $yy, $l, 9);
        $yy += 4.5;
    }
    $y = max($y + 26, $yy + 4);

    $grand = 0.0;
    $grandShip = 0.0;
    foreach ($pos as $po) {
        if ($y > 230) {
            $pdf->addPage();
            $header();
        }
        // Livraison
        $pdf->rect(15, $y - 4, 180, 7, [244, 246, 251]);
        $pdf->text(17, $y + 1, 'LIVRAISON : ' . mb_strtoupper((string)$po['center_name']) . ($grouped ? '   —   bon ' . $po['po_number'] : ''), 9, true, $violet);
        $y += 8;
        $addr = trim($po['center_address'] . ', ' . $po['center_city'], ', ');
        $pdf->text(17, $y, $addr . ($po['center_phone'] ? '   Tél. ' . $po['center_phone'] : ''), 9);
        $y += 4.5;
        if ($po['delivery_info']) {
            foreach ($pdf->wrap((string)$po['delivery_info'], 175, 8.5) as $l) {
                $pdf->text(17, $y, $l, 8.5, false, $muted);
                $y += 4;
            }
        }
        $y += 3;
        // En-tête du tableau
        $cols = [[15, 28, 'Référence', 'L'], [43, 80, 'Désignation', 'L'], [123, 14, 'Qté', 'R'], [137, 28, 'P.U. HT', 'R'], [165, 30, 'Total HT', 'R']];
        foreach ($cols as [$x, $w, $t, $a]) {
            $pdf->text($x, $y, $t, 8, true, $muted, $a, $w);
        }
        $y += 2;
        $pdf->line(15, $y, 195, $y);
        $y += 5;
        $sub = 0.0;
        foreach ($po['lines'] as $l) {
            $label = $pdf->wrap($l['label'] . ($l['unit'] ? ' (' . $l['unit'] . ')' : ''), 78, 9);
            if ($y + count($label) * 4.2 > 275) {
                $pdf->addPage();
                $header();
            }
            $total = (int)$l['qty'] * (float)$l['unit_price'];
            $sub += $total;
            $pdf->text(15, $y, (string)$l['reference'], 8.5);
            foreach ($label as $i => $t) {
                $pdf->text(43, $y + $i * 4.2, $t, 9);
            }
            $pdf->text(123, $y, (string)$l['qty'], 9, true, [30, 35, 53], 'R', 14);
            $pdf->text(137, $y, money($l['unit_price']), 9, false, [30, 35, 53], 'R', 28);
            $pdf->text(165, $y, money($total), 9, false, [30, 35, 53], 'R', 30);
            $y += max(1, count($label)) * 4.2 + 2;
            $pdf->line(15, $y - 3, 195, $y - 3, [236, 238, 244], 0.2);
        }
        $grand += $sub;
        $grandShip += (float)$po['shipping_fee'];
        $pdf->text(120, $y + 1, $grouped ? 'Sous-total ' . $po['center_name'] : 'Sous-total HT', 9, false, $muted, 'R', 45);
        $pdf->text(165, $y + 1, money($sub), 9, true, [30, 35, 53], 'R', 30);
        $y += 10;
        if ($po['notes']) {
            foreach ($pdf->wrap('Instructions : ' . $po['notes'], 180, 8.5) as $l) {
                $pdf->text(15, $y, $l, 8.5, false, $muted);
                $y += 4;
            }
            $y += 2;
        }
    }
    if ($y > 250) {
        $pdf->addPage();
        $header();
    }
    $pdf->line(110, $y, 195, $y, $violet, 0.5);
    $y += 6;
    foreach ([['Total articles HT', $grand, false], ['Frais de port HT', $grandShip, false], ['TOTAL HT', $grand + $grandShip, true]] as [$t, $v, $b]) {
        $pdf->text(110, $y, $t, $b ? 11 : 9, $b, $b ? $violet : $muted, 'R', 55);
        $pdf->text(165, $y, money($v), $b ? 11 : 9, $b, $b ? $violet : [30, 35, 53], 'R', 30);
        $y += $b ? 8 : 5;
    }
    $y += 6;
    foreach (array_filter([setting('billing_info') ? 'Facturation : ' . setting('billing_info') : null,
                 'Merci de rappeler la référence ' . ($grouped ? $first['group_ref'] . ' et les numéros de bon' : $first['po_number']) . ' sur les bons de livraison et la facture.']) as $t) {
        foreach ($pdf->wrap((string)$t, 180, 8.5) as $l) {
            $pdf->text(15, $y, $l, 8.5, false, $muted);
            $y += 4;
        }
    }
    return $pdf->output();
}
