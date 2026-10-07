<?php
declare(strict_types=1);

/**
 * Codes-barres en SVG (sans bibliothèque) pour les étiquettes : EAN-13 / EAN-8 quand le code s'y prête
 * (clé de contrôle valide), Code 128 sinon (références fournisseur, codes internes).
 */

/**
 * Formats d'étiquettes (mm) : planches A4 prédécoupées ou rouleau (imprimante d'étiquettes, une étiquette par page).
 * t/l : marges haute et gauche de la planche, gx/gy : espaces entre étiquettes, fs : tailles (nom pt, texte pt, hauteur code-barres mm).
 */
const LABEL_FORMATS = [
    'a4-24' => ['label' => 'Planche A4 · 24 étiquettes 70 × 37 mm', 'w' => 70, 'h' => 37, 'cols' => 3, 'rows' => 8, 't' => 4.5, 'l' => 0, 'gx' => 0, 'gy' => 0, 'fs' => [9.5, 7, 9]],
    'a4-14' => ['label' => 'Planche A4 · 14 étiquettes 99 × 38 mm', 'w' => 99.1, 'h' => 38.1, 'cols' => 2, 'rows' => 7, 't' => 15.15, 'l' => 4.65, 'gx' => 2.5, 'gy' => 0, 'fs' => [11, 8, 10]],
    'a4-8'  => ['label' => 'Planche A4 · 8 grandes étiquettes 105 × 74 mm', 'w' => 105, 'h' => 74, 'cols' => 2, 'rows' => 4, 't' => 0.5, 'l' => 0, 'gx' => 0, 'gy' => 0, 'fs' => [16, 10.5, 18]],
    'roll-100x50' => ['label' => 'Rouleau · 100 × 50 mm (imprimante d\'étiquettes)', 'w' => 100, 'h' => 50, 'roll' => true, 'fs' => [13, 9, 14]],
    'roll-62x29'  => ['label' => 'Rouleau · 62 × 29 mm (Brother QL, Dymo…)', 'w' => 62, 'h' => 29, 'roll' => true, 'fs' => [8, 6.5, 7.5]],
];

const CODE128_PATTERNS = [
    '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
    '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
    '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
    '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
    '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
    '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
    '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
    '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
    '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
    '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
    '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
];

const EAN_L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
const EAN_G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
const EAN_R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
const EAN13_PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

/** Clé de contrôle EAN/UPC d'une suite de chiffres (sans la clé). */
function ean_check_digit(string $digits): int
{
    $sum = 0;
    $len = strlen($digits);
    for ($i = 0; $i < $len; $i++) {
        $sum += (int)$digits[$len - 1 - $i] * ($i % 2 === 0 ? 3 : 1);
    }
    return (10 - $sum % 10) % 10;
}

function ean_valid(string $code): bool
{
    return (bool)preg_match('/^(\d{8}|\d{12}|\d{13})$/', $code) && ean_check_digit(substr($code, 0, -1)) === (int)substr($code, -1);
}

/** Barres d'un code ('1' = barre, '0' = espace, sans zones blanches) et symbologie retenue. */
function barcode_encode(string $code): array
{
    $code = trim($code);
    if (ean_valid($code)) {
        if (strlen($code) === 12) { // UPC-A = EAN-13 commençant par 0
            $code = '0' . $code;
        }
        if (strlen($code) === 13) {
            $bits = '101';
            $parity = EAN13_PARITY[(int)$code[0]];
            for ($i = 1; $i <= 6; $i++) {
                $bits .= ($parity[$i - 1] === 'L' ? EAN_L : EAN_G)[(int)$code[$i]];
            }
            $bits .= '01010';
            for ($i = 7; $i <= 12; $i++) {
                $bits .= EAN_R[(int)$code[$i]];
            }
            return ['type' => 'EAN-13', 'text' => $code, 'bits' => $bits . '101', 'quiet' => [11, 7]];
        }
        $bits = '101';
        for ($i = 0; $i < 4; $i++) {
            $bits .= EAN_L[(int)$code[$i]];
        }
        $bits .= '01010';
        for ($i = 4; $i < 8; $i++) {
            $bits .= EAN_R[(int)$code[$i]];
        }
        return ['type' => 'EAN-8', 'text' => $code, 'bits' => $bits . '101', 'quiet' => [7, 7]];
    }
    return ['type' => 'Code 128', 'text' => $code, 'bits' => code128_bits($code), 'quiet' => [10, 10]];
}

/** Code 128 : jeu C (chiffres par paires) quand c'est plus court, jeu B sinon ; caractères hors ASCII remplacés. */
function code128_bits(string $text): string
{
    $text = preg_replace('/[^\x20-\x7E]/', '?', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text);
    $values = [];
    $set = null;
    $i = 0;
    $n = strlen($text);
    while ($i < $n) {
        // Bloc de chiffres assez long pour le jeu C (4 et plus, ou tout le code s'il n'est fait que de chiffres pairs)
        preg_match('/^\d*/', substr($text, $i), $m);
        $run = strlen($m[0]);
        if ($run >= 4 || ($run === $n && $run % 2 === 0 && $run >= 2)) {
            $run -= $run % 2;
            $values[] = $set === null ? 105 : 99;
            $set = 'C';
            for ($k = 0; $k < $run; $k += 2) {
                $values[] = (int)substr($text, $i + $k, 2);
            }
            $i += $run;
            continue;
        }
        if ($set !== 'B') {
            $values[] = $set === null ? 104 : 100;
            $set = 'B';
        }
        $values[] = ord($text[$i]) - 32;
        $i++;
    }
    $sum = $values[0];
    foreach (array_slice($values, 1) as $pos => $v) {
        $sum += ($pos + 1) * $v;
    }
    $values[] = $sum % 103;
    $values[] = 106;
    $bits = '';
    foreach ($values as $v) {
        $bar = true;
        foreach (str_split(CODE128_PATTERNS[$v]) as $w) {
            $bits .= str_repeat($bar ? '1' : '0', (int)$w);
            $bar = !$bar;
        }
    }
    return $bits;
}

/**
 * Code-barres SVG. $maxWidthMm : largeur disponible ; le module (barre la plus fine) vaut au plus 0,33 mm
 * (taille nominale EAN) et au moins 0,19 mm pour rester lisible par une douchette ou un smartphone.
 */
function barcode_svg(string $code, float $maxWidthMm, float $heightMm): array
{
    $b = barcode_encode($code);
    $modules = strlen($b['bits']) + $b['quiet'][0] + $b['quiet'][1];
    $module = min(0.33, $maxWidthMm / $modules);
    $rects = '';
    $x = $b['quiet'][0];
    foreach (preg_split('/(0+)/', $b['bits'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $run) {
        if ($run[0] === '1') {
            $rects .= '<rect x="' . $x . '" y="0" width="' . strlen($run) . '" height="1"/>';
        }
        $x += strlen($run);
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $modules . ' 1" preserveAspectRatio="none" shape-rendering="crispEdges"'
        . ' style="width:' . round($modules * $module, 2) . 'mm;height:' . $heightMm . 'mm;display:block" role="img" aria-label="' . e($b['type'] . ' ' . $b['text']) . '">'
        . '<rect width="' . $modules . '" height="1" fill="#fff"/><g fill="#000">' . $rects . '</g></svg>';
    return ['svg' => $svg, 'type' => $b['type'], 'text' => $b['text'], 'module' => $module, 'readable' => $module >= 0.19];
}
