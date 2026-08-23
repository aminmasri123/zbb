<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjektLuvTemplate extends Model
{
    use HasFactory;

    public const DEFAULT_SECTIONS = [
        [
            'key' => 'ausgangssituation',
            'heading' => 'Ausgangssituation',
            'instruction' => 'Beschreibe die belegte Ausgangssituation sachlich und knapp.',
            'required' => true,
        ],
        [
            'key' => 'zielvereinbarung',
            'heading' => 'Zielvereinbarung und Fortschritt',
            'instruction' => 'Stelle vereinbarte Ziele, beobachtete Fortschritte und offene Schritte gegenüber.',
            'required' => true,
        ],
        [
            'key' => 'qualifikationen',
            'heading' => 'Qualifikationen',
            'instruction' => 'Nenne ausschließlich nachgewiesene Qualifikationen und Kompetenzen.',
            'required' => false,
        ],
        [
            'key' => 'anwesenheit',
            'heading' => 'Anwesenheit',
            'instruction' => 'Fasse die bereitgestellten Anwesenheitsdaten neutral zusammen.',
            'required' => true,
        ],
        [
            'key' => 'empfehlungen',
            'heading' => 'Weitere Entwicklung und Empfehlungen',
            'instruction' => 'Formuliere realistische nächste Schritte, ohne unbelegte Tatsachen hinzuzufügen.',
            'required' => false,
        ],
    ];

    public const SUPPORTED_PLACEHOLDERS = [
        'typ', 'zeitraumStart', 'zeitraumBis', 'geburtsdatum', 'kundennummer',
        'vorname', 'nachname', 'vermittler', 'betreuer', 'projekt',
        'zuweisungVon', 'zuweisungBis', 'ausgangssituation', 'zielvereinbarung',
        'qualifikationen', 'listeErstellteLuvs', 'A', 'U', 'F', 'E', 'K',
        'PAU', 'PKE', 'PF', 'listeBereiche',
    ];

    protected $fillable = [
        'projekt_id',
        'version',
        'name',
        'original_filename',
        'file_path',
        'sections',
        'ai_instructions',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function aiConfiguration(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'sections' => $this->sections ?: self::DEFAULT_SECTIONS,
            'instructions' => $this->ai_instructions ?: null,
        ];
    }

    public static function defaultAiConfiguration(): array
    {
        return [
            'name' => 'Standard-LuV',
            'version' => null,
            'sections' => self::DEFAULT_SECTIONS,
            'instructions' => null,
        ];
    }
}
