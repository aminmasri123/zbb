<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjektLuvTemplate extends Model
{
    use HasFactory;

    public const TYPES = ['Start', 'Verlauf', 'Abschluss'];

    public const REPORT_TYPE_MAP = [
        'Start' => 'luv',
        'Verlauf' => 'interim',
        'Abschluss' => 'final',
    ];

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

    public const DEFAULT_SECTIONS_BY_TYPE = [
        'Start' => [
            ['key' => 'ausgangssituation', 'heading' => 'Individuelle Ausgangssituation', 'instruction' => 'Fasse ausschließlich belegte Beobachtungen zu schulischen, personalen, methodischen, sozial-kommunikativen und fachlichen Kompetenzen zusammen und trenne Einschätzung und Förderbedarf.', 'required' => true],
            ['key' => 'foerdersequenzen', 'heading' => 'Förderzielbereiche und Qualifizierungssequenzen', 'instruction' => 'Nenne belegte Förderzielbereiche, geplante Sequenzen und Zeiträume. Nicht belegte Auswahlentscheidungen bleiben offen.', 'required' => true],
            ['key' => 'eingliederungsziel', 'heading' => 'Eingliederungsziel', 'instruction' => 'Beschreibe den dokumentierten Zielberuf, Alternativen und die Zielsetzung Ausbildung oder Beschäftigung.', 'required' => true],
            ['key' => 'zielvereinbarung', 'heading' => 'Schritte zur Zielerreichung', 'instruction' => 'Formuliere die aktuelle Zielvereinbarung getrennt nach Aufgaben der teilnehmenden Person, des Maßnahmepersonals und gemeinsamen Aufgaben.', 'required' => true],
            ['key' => 'entscheidungsbedarf', 'heading' => 'Andere entscheidungsbedürftige Aspekte', 'instruction' => 'Nenne nur ausdrücklich dokumentierten Entscheidungsbedarf. Diagnosen und Gesundheitsdaten dürfen nicht ausgegeben werden.', 'required' => false],
        ],
        'Verlauf' => [
            ['key' => 'entwicklung', 'heading' => 'Individuelle Entwicklung', 'instruction' => 'Vergleiche bisherigen und aktuellen Förderbedarf je Kompetenzbereich anhand belegter Beobachtungen.', 'required' => true],
            ['key' => 'foerdersequenzen', 'heading' => 'Förderzielbereiche und Qualifizierungssequenzen', 'instruction' => 'Stelle geplante, laufende und belegbar abgeschlossene Sequenzen mit Zeitraum dar.', 'required' => true],
            ['key' => 'praktika_qualifikationen', 'heading' => 'Praktika und Qualifikationen', 'instruction' => 'Fasse projektbezogene Praktika, Qualifizierungsbausteine und Ergebnisse sachlich zusammen.', 'required' => false],
            ['key' => 'eingliederungsziel', 'heading' => 'Eingliederungsziel', 'instruction' => 'Beschreibe den aktuellen Zielberuf und dokumentierte Alternativen.', 'required' => true],
            ['key' => 'zielvereinbarung', 'heading' => 'Schritte zur Zielerreichung', 'instruction' => 'Leite Aufgaben ausschließlich aus dem dokumentierten aktuellen Förderbedarf und den Zielvereinbarungen ab.', 'required' => true],
            ['key' => 'entscheidungsbedarf', 'heading' => 'Andere entscheidungsbedürftige Aspekte', 'instruction' => 'Nenne nur belegten Entscheidungsbedarf, insbesondere bei Maßnahmeverlängerung, Teilzeit oder drohendem Abbruch.', 'required' => false],
        ],
        'Abschluss' => [
            ['key' => 'ergebnisse', 'heading' => 'Ergebnisse der BvB', 'instruction' => 'Fasse belegte Abschlüsse, Kompetenzen, Qualifizierungsbausteine und den Entwicklungsstand zum Maßnahmeende zusammen.', 'required' => true],
            ['key' => 'praktika_qualifikationen', 'heading' => 'Praktika und Qualifikationen', 'instruction' => 'Nenne projektbezogene Praktika und nachgewiesene Qualifikationen mit Zeitraum und Ergebnis.', 'required' => false],
            ['key' => 'eingliederung', 'heading' => 'Eingliederungsergebnis', 'instruction' => 'Beschreibe ausschließlich belegte Angaben zu Betrieb, Beruf, Zeitpunkt und Beschäftigungs- oder Ausbildungsstatus.', 'required' => true],
            ['key' => 'unterstuetzungsbedarf', 'heading' => 'Unterstützungsbedarf und Stabilisierung', 'instruction' => 'Formuliere nur fachlich bestätigten Unterstützungsbedarf und dokumentierte Absprachen.', 'required' => false],
            ['key' => 'empfehlungen', 'heading' => 'Ergänzende Erläuterungen und Empfehlungen', 'instruction' => 'Formuliere realistische, belegte Empfehlungen. Ausbildungsreife und Berufseignung dürfen nicht aus indirekten Daten geraten werden.', 'required' => false],
        ],
    ];

    public const DEFAULT_SOURCE_SETTINGS = [
        'identity' => true,
        'attendance' => true,
        'documentation' => true,
        'previous_luvs' => true,
        'internships' => true,
        'education' => true,
        'consents' => true,
    ];

    public const DEFAULT_SCHEDULE_SETTINGS = [
        'Start' => ['enabled' => true, 'trigger' => 'competency_analysis_end', 'offset_days' => 14],
        'Verlauf' => ['enabled' => true, 'trigger' => 'manual_or_due_date', 'offset_days' => 0],
        'Abschluss' => ['enabled' => true, 'trigger' => 'participation_end', 'offset_days' => 0],
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
        'luv_type',
        'version',
        'name',
        'form_version',
        'original_filename',
        'template_format',
        'file_path',
        'sections',
        'field_schema',
        'source_settings',
        'schedule_settings',
        'ai_instructions',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'field_schema' => 'array',
        'source_settings' => 'array',
        'schedule_settings' => 'array',
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
        $type = self::normalizeType($this->luv_type);

        return [
            'name' => $this->name,
            'version' => $this->version,
            'luv_type' => $type,
            'form_version' => $this->form_version,
            'sections' => $this->sections ?: self::defaultSectionsFor($type),
            'field_schema' => $this->field_schema ?: self::defaultFieldSchemaFor($type),
            'sources' => array_replace(self::DEFAULT_SOURCE_SETTINGS, $this->source_settings ?: []),
            'schedule' => $this->schedule_settings ?: self::DEFAULT_SCHEDULE_SETTINGS[$type],
            'instructions' => $this->ai_instructions ?: null,
        ];
    }

    public static function defaultAiConfiguration(string $type = 'Start'): array
    {
        $type = self::normalizeType($type);

        return [
            'name' => "Standard-{$type}-LuV",
            'version' => null,
            'luv_type' => $type,
            'form_version' => 'BA-BvB-2023',
            'sections' => self::defaultSectionsFor($type),
            'field_schema' => self::defaultFieldSchemaFor($type),
            'sources' => self::DEFAULT_SOURCE_SETTINGS,
            'schedule' => self::DEFAULT_SCHEDULE_SETTINGS[$type],
            'instructions' => null,
        ];
    }

    public static function defaultSectionsFor(string $type): array
    {
        return self::DEFAULT_SECTIONS_BY_TYPE[self::normalizeType($type)];
    }

    public static function defaultFieldSchemaFor(string $type): array
    {
        $common = [
            ['key' => 'participant.first_name', 'label' => 'Vorname', 'type' => 'text', 'source' => 'identity', 'required' => true, 'ai_writable' => false],
            ['key' => 'participant.last_name', 'label' => 'Nachname', 'type' => 'text', 'source' => 'identity', 'required' => true, 'ai_writable' => false],
            ['key' => 'participant.customer_number', 'label' => 'Kundennummer', 'type' => 'text', 'source' => 'identity', 'required' => false, 'ai_writable' => false],
            ['key' => 'report.discussed_on', 'label' => 'Besprochen am', 'type' => 'date', 'source' => 'manual', 'required' => true, 'ai_writable' => false],
        ];

        $typeFields = match (self::normalizeType($type)) {
            'Start' => [
                ['key' => 'initial_situation', 'label' => 'Individuelle Ausgangssituation', 'type' => 'long_text', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
                ['key' => 'funding_sequences', 'label' => 'Förder- und Qualifizierungssequenzen', 'type' => 'structured_list', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
                ['key' => 'integration_goal', 'label' => 'Eingliederungsziel', 'type' => 'long_text', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
                ['key' => 'goal_steps', 'label' => 'Schritte zur Zielerreichung', 'type' => 'long_text', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
            ],
            'Verlauf' => [
                ['key' => 'development', 'label' => 'Individuelle Entwicklung', 'type' => 'long_text', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
                ['key' => 'funding_sequences', 'label' => 'Förder- und Qualifizierungssequenzen', 'type' => 'structured_list', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
                ['key' => 'internships', 'label' => 'Praktika und Qualifikationen', 'type' => 'structured_list', 'source' => 'internships', 'required' => false, 'ai_writable' => true],
                ['key' => 'goal_steps', 'label' => 'Schritte zur Zielerreichung', 'type' => 'long_text', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
            ],
            'Abschluss' => [
                ['key' => 'results', 'label' => 'Ergebnisse der BvB', 'type' => 'long_text', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
                ['key' => 'integration_result', 'label' => 'Eingliederungsergebnis', 'type' => 'long_text', 'source' => 'ai', 'required' => true, 'ai_writable' => true],
                ['key' => 'support_required', 'label' => 'Unterstützungsbedarf', 'type' => 'boolean_with_text', 'source' => 'manual', 'required' => true, 'ai_writable' => false],
                ['key' => 'recommendations', 'label' => 'Empfehlungen', 'type' => 'long_text', 'source' => 'ai', 'required' => false, 'ai_writable' => true],
            ],
        };

        return array_merge($common, $typeFields);
    }

    public static function normalizeType(?string $type): string
    {
        return in_array($type, self::TYPES, true) ? $type : 'Start';
    }

    public static function fromReportType(string $reportType): string
    {
        return array_search($reportType, self::REPORT_TYPE_MAP, true) ?: 'Start';
    }

    public static function toReportType(string $type): string
    {
        return self::REPORT_TYPE_MAP[self::normalizeType($type)];
    }
}
