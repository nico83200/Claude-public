<?php

namespace App\Support;

/** Couleurs des pastilles (composant x-badge, statuts calculés). */
final class Badge
{
    public const CLASSES = [
        'slate' => 'bg-slate-100 text-slate-700', 'green' => 'bg-emerald-100 text-emerald-800', 'red' => 'bg-red-100 text-red-800',
        'amber' => 'bg-amber-100 text-amber-800', 'blue' => 'bg-sky-100 text-sky-800', 'purple' => 'bg-violet-100 text-violet-800',
        'brand' => 'bg-brand-100 text-brand-800',
    ];

    public static function classes(string $color): string
    {
        return self::CLASSES[$color] ?? self::CLASSES['slate'];
    }
}
