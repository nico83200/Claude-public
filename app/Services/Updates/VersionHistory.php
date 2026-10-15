<?php

namespace App\Services\Updates;

use App\Models\AppUpdate;
use App\Support\Installation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Historique des versions (Administration › Mises à jour) : principaux changements de chaque version
 * (resources/data/versions.php) et date à laquelle elle a été installée sur ce serveur.
 *
 * Une version sautée (2.17.0 → 2.19.0) est installée avec la mise à jour qui l'a dépassée ;
 * une version antérieure à la première mise à jour enregistrée date de l'installation initiale.
 */
class VersionHistory
{
    public function __construct(private Updater $updater) {}

    /** @return Collection<int, array{version: string, released: Carbon|null, summary: string, installed_at: Carbon|null, with: string|null, initial: bool, current: bool}> */
    public function rows(): Collection
    {
        $current = $this->updater->currentVersion();
        $updates = AppUpdate::where('status', 'done')->whereNotNull('to_version')->get()
            ->sortBy(fn (AppUpdate $u) => ($u->finished_at ?? $u->updated_at)->getTimestamp())->values();
        $firstFrom = $updates->first()?->from_version;
        $installedAt = $this->initialInstallDate();

        return collect(require resource_path('data/versions.php'))
            ->filter(fn ($row, $version) => version_compare((string) $version, $current, '<='))
            ->map(function (array $row, $version) use ($updates, $firstFrom, $installedAt, $current) {
                $version = (string) $version;
                $update = $updates->first(fn (AppUpdate $u) => version_compare((string) $u->from_version, $version, '<') && version_compare((string) $u->to_version, $version, '>='));
                $released = rescue(fn () => Carbon::parse($row[0]), null, false);
                // Installation initiale : version antérieure à la première mise à jour, et publiée avant l'installation.
                $initial = ! $update && ($firstFrom === null || version_compare($version, $firstFrom, '<='))
                    && ! ($released && $installedAt && $released->copy()->startOfDay()->gt($installedAt));

                return [
                    'version' => $version,
                    'released' => $released,
                    'summary' => $row[1],
                    'installed_at' => $update ? ($update->finished_at ?? $update->updated_at) : ($initial ? $installedAt : null),
                    'with' => $update && $update->to_version !== $version ? $update->to_version : null,
                    'initial' => $initial,
                    'current' => $version === $current,
                ];
            })->values();
    }

    private function initialInstallDate(): ?Carbon
    {
        $lock = Installation::lockPath();
        $data = is_file($lock) ? json_decode((string) file_get_contents($lock), true) : null;

        return isset($data['installed_at']) ? rescue(fn () => Carbon::parse($data['installed_at']), null, false) : null;
    }
}
