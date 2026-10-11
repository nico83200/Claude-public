<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pesée ou estimation du poids d'un cheval, et note d'état corporel. */
class HorseMeasurement extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['measured_on' => 'date', 'weight_kg' => 'float'];

    public const METHODS = ['scale' => 'Pesée (balance)', 'tape' => 'Ruban et mètre', 'estimate' => 'Estimation'];

    /** Note d'état corporel de Henneke (1 à 9). */
    public const BODY_CONDITION = [
        1 => '1 · Très maigre', 2 => '2 · Maigre', 3 => '3 · Mince', 4 => '4 · Un peu mince', 5 => '5 · Idéal',
        6 => '6 · Un peu enrobé', 7 => '7 · Enrobé', 8 => '8 · Gras', 9 => '9 · Obèse',
    ];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Poids estimé à partir du tour de poitrine et de la longueur du corps (formule de Carroll & Huntington) :
     * poids (kg) = tour de poitrine² (cm) × longueur (cm) ÷ 11 877.
     */
    public static function estimateFromTape(int $girthCm, int $lengthCm): float
    {
        return round($girthCm ** 2 * $lengthCm / 11877, 1);
    }
}
