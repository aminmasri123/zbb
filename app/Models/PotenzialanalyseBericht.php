<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PotenzialanalyseBericht extends Model
{
    use HasFactory;

    public const LUV_FOERDERBEDARF_BEREICHE = [
        'personal' => [
            'label' => 'Personale Kompetenz',
            'field_key' => 'competence.personal.support_need',
        ],
        'methodical' => [
            'label' => 'Methodische Kompetenz',
            'field_key' => 'competence.methodical.support_need',
        ],
        'social' => [
            'label' => 'Sozial-kommunikative Kompetenz',
            'field_key' => 'competence.social.support_need',
        ],
    ];

    protected $table = 'potenzialanalyse_berichte';

    protected $fillable = [
        'gruppe_id',
        'personen_id',
        'user_id',
        'status',
        'staerken',
        'entwicklungsfelder',
        'empfehlung',
        'bericht_text',
        'generator_stil',
        'generator_snapshot',
        'luv_foerderbedarfe',
        'fertiggestellt_at',
    ];

    protected $casts = [
        'fertiggestellt_at' => 'datetime',
        'generator_snapshot' => 'array',
        'luv_foerderbedarfe' => 'array',
    ];

    public static function defaultLuvFoerderbedarfe(): array
    {
        return collect(self::LUV_FOERDERBEDARF_BEREICHE)
            ->mapWithKeys(fn (array $definition, string $key) => [$key => [
                'status' => 'unprueft',
                'begruendung' => '',
                'foerderbedarf' => '',
                'freigegeben' => false,
                'freigegeben_von' => null,
                'freigegeben_am' => null,
            ]])
            ->all();
    }

    public function gruppe(): BelongsTo
    {
        return $this->belongsTo(Gruppe::class);
    }
}
