<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Bibliothèque des illustrations Crinière Club (database/designs/lancement) :
 * fichiers d'impression HD en deux coloris et réglages de la zone du nom pour les visuels personnalisables.
 */
final class DesignLibrary
{
    private const DIR = BASE_PATH . '/database/designs/lancement';

    public const COLORS = [
        'bordeaux' => ['Bordeaux — pour supports clairs', '#5a2a3a'],
        'creme'    => ['Crème — pour supports foncés', '#f9f7f3'],
    ];

    /** @return array<string, array{title:string, personalizable:bool, preset:?array}> */
    public static function all(): array
    {
        $file = self::DIR . '/manifest.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($data) ? $data : [];
    }

    /**
     * Copie le visuel choisi dans storage/designs et renvoie le nom du fichier
     * et, pour un visuel personnalisable, le réglage de personnalisation (couleur comprise).
     * @return array{file:string, preset:?array, title:string}
     */
    public static function install(string $key, string $color): array
    {
        $designs = self::all();
        if (!isset($designs[$key], self::COLORS[$color]) || !preg_match('/^[a-z0-9-]+$/', $key)) {
            throw new \RuntimeException('Visuel de bibliothèque inconnu.');
        }
        $source = self::DIR . '/' . $key . '-' . $color . '.png';
        if (!is_file($source)) {
            throw new \RuntimeException('Fichier du visuel introuvable : ' . basename($source));
        }
        $name = 'bibliotheque-' . $key . '-' . $color . '.png';
        if (!copy($source, STORAGE_PATH . '/designs/' . $name)) {
            throw new \RuntimeException('Impossible de copier le visuel dans storage/designs.');
        }
        $preset = $designs[$key]['preset'] ?? null;
        if ($preset) {
            $preset['color'] = self::COLORS[$color][1];
            $preset['preview_color'] = self::COLORS[$color][1];
        }
        return ['file' => $name, 'preset' => $preset, 'title' => $designs[$key]['title']];
    }
}
