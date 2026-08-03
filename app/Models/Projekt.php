<?php

namespace App\Models;
use App\Models\Abteilung;
use App\Models\Bereich;
use App\Models\DokumentKategorie;
use App\Models\Dokumente;
use App\Models\Kostenstelle;
use App\Models\Partner;
use App\Models\PartnerHasPartnerschaftstypen;
use App\Models\Personen;
use App\Models\PotenzialanalyseUebung;
use App\Models\ProjektHasAnsprechpartner;
use App\Models\ProjektHasPartner;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\User;
use App\Models\Zeitraum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Projekt extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'project_type_id',
        'name',
        'abteilung_id',
        'beschreibung',
        'aktiv',
        'klassenbuch_aktiv',
        'potenzialanalyse_aktiv',
        'potenzialanalyse_tage',
        'feature_settings',
        'rule_settings',
        'portal_feature_settings',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
        'klassenbuch_aktiv' => 'boolean',
        'potenzialanalyse_aktiv' => 'boolean',
        'potenzialanalyse_tage' => 'integer',
        'feature_settings' => 'array',
        'rule_settings' => 'array',
        'portal_feature_settings' => 'array',
    ];

    public const FEATURE_DEFAULTS = [
        'participant_management' => true,
        'group_management' => true,
        'attendance_management' => true,
        'internship_management' => true,
        'completion_management' => true,
    ];

    public const FEATURE_DEPENDENCIES = [
        'group_management' => ['participant_management'],
        'attendance_management' => ['participant_management'],
        'internship_management' => ['participant_management'],
        'completion_management' => ['participant_management'],
        'classbook_management' => ['group_management'],
        'potential_analysis' => ['participant_management', 'group_management'],
    ];

    public const RULE_DEFAULTS = [
        'max_group_participants' => null,
        'attendance_skip_weekends' => false,
        'attendance_default_status' => 'unentschuldigt',
        'participant_birthdate_required' => false,
        'participant_min_age' => null,
        'participant_max_age' => null,
        'participation_initial_status' => 'aktiv',
        'participant_overview_columns' => [],
        'participant_overview_show_metrics' => true,
    ];

    public const PARTICIPANT_OVERVIEW_COLUMN_DEFINITIONS = [
        'id' => [
            'label' => 'ID',
            'description' => 'Interne Teilnehmernummer.',
            'sortable' => 'id',
        ],
        'parental_consent' => [
            'label' => 'Elterneinverstaendnis',
            'description' => 'Status der Elterneinverstaendniserklaerung bei BOP-Schuelern.',
        ],
        'first_name' => [
            'label' => 'Vorname',
            'description' => 'Vorname des Teilnehmers.',
            'sortable' => 'vorname',
        ],
        'last_name' => [
            'label' => 'Nachname',
            'description' => 'Nachname des Teilnehmers.',
            'sortable' => 'nachname',
        ],
        'gender' => [
            'label' => 'Geschlecht',
            'description' => 'Geschlecht aus den Stammdaten.',
            'sortable' => 'geschlecht',
        ],
        'school_class' => [
            'label' => 'Klasse',
            'description' => 'Klasse aus der Schulzuordnung.',
        ],
        'school' => [
            'label' => 'Schule',
            'description' => 'Schule, Schuljahr und Teilabschnitt.',
        ],
        'visited_areas' => [
            'label' => 'Besuchte Bereiche',
            'description' => 'Bereiche aus Einteilung oder Gruppenteilnahme.',
        ],
        'participation' => [
            'label' => 'Teilnahme',
            'description' => 'Teilnahmestatus und Standort.',
        ],
        'group_supervisor' => [
            'label' => 'Gruppe / Betreuung',
            'description' => 'Gruppen, Bereiche und verantwortliche Betreuung.',
        ],
        'period_balance' => [
            'label' => 'Monat',
            'description' => 'Saldo und Quote im gewaehlten Monat.',
        ],
        'total_balance' => [
            'label' => 'Gesamtlaufzeit',
            'description' => 'Saldo und Quote ueber die gesamte Projektlaufzeit.',
        ],
        'absences' => [
            'label' => 'Fehlzeiten',
            'description' => 'Fehltage und unentschuldigte Fehltage im gewaehlten Monat.',
        ],
        'tasks' => [
            'label' => 'Aufgaben',
            'description' => 'Offene und ueberfaellige Aufgaben.',
        ],
        'measures' => [
            'label' => 'Praktika / Massnahmen',
            'description' => 'Aktive Praktika oder Bildungsmassnahmen.',
        ],
    ];

    public const DEFAULT_PARTICIPANT_OVERVIEW_COLUMNS = [
        'id',
        'first_name',
        'last_name',
        'participation',
        'group_supervisor',
        'period_balance',
        'total_balance',
        'absences',
        'tasks',
        'measures',
        'gender',
    ];

    public const BOP_PARTICIPANT_OVERVIEW_COLUMNS = [
        'id',
        'parental_consent',
        'first_name',
        'last_name',
        'gender',
        'school_class',
        'school',
        'visited_areas',
    ];

    public const PARTICIPATION_STATUSES = [
        'angefragt',
        'angemeldet',
        'aufgenommen',
        'aktiv',
        'pausiert',
        'abgeschlossen',
        'abgebrochen',
    ];

    public const PORTAL_FEATURE_DEFAULTS = [
        'profile' => true,
        'attendance_self_service' => false,
        'tasks_and_appointments' => true,
        'job_search' => false,
        'application_management' => false,
        'learning' => false,
        'messaging' => false,
        'consents_and_approvals' => false,
    ];

    public function portalFeatureSettings(): array
    {
        return array_replace(self::PORTAL_FEATURE_DEFAULTS, $this->portal_feature_settings ?? []);
    }

    public function portalFeatureEnabled(string $key): bool
    {
        return (bool) ($this->portalFeatureSettings()[$key] ?? false);
    }

    public function ruleSettings(): array
    {
        $settings = array_replace(self::RULE_DEFAULTS, $this->rule_settings ?? []);
        $settings['participant_overview_columns'] = $this->participantOverviewColumns();
        $settings['participant_overview_show_metrics'] = $this->participantOverviewShowsMetrics();

        return $settings;
    }

    public function rule(string $key, mixed $default = null): mixed
    {
        return $this->ruleSettings()[$key] ?? $default;
    }

    public function featureSettings(): array
    {
        return collect($this->configuredFeatureSettings())
            ->mapWithKeys(fn ($enabled, $key) => [$key => $this->featureEnabled($key)])
            ->all();
    }

    public function configuredFeatureSettings(): array
    {
        return array_replace(self::FEATURE_DEFAULTS, $this->feature_settings ?? [], [
            'classbook_management' => (bool) $this->klassenbuch_aktiv,
            'potential_analysis' => (bool) $this->potenzialanalyse_aktiv,
        ]);
    }

    public function featureEnabled(string $key): bool
    {
        $settings = $this->configuredFeatureSettings();

        if (!(bool) ($settings[$key] ?? false)) {
            return false;
        }

        foreach (self::FEATURE_DEPENDENCIES[$key] ?? [] as $dependency) {
            if (!$this->featureEnabled($dependency)) {
                return false;
            }
        }

        return true;
    }

    public static function participantOverviewColumnDefinitions(): array
    {
        return collect(self::PARTICIPANT_OVERVIEW_COLUMN_DEFINITIONS)
            ->map(fn (array $definition, string $key) => array_merge(['key' => $key], $definition))
            ->values()
            ->all();
    }

    public static function participantOverviewColumnKeys(): array
    {
        return array_keys(self::PARTICIPANT_OVERVIEW_COLUMN_DEFINITIONS);
    }

    public static function normalizeParticipantOverviewColumns(?array $columns): array
    {
        $validColumns = collect($columns ?? [])
            ->filter(fn ($column) => is_string($column))
            ->unique()
            ->intersect(self::participantOverviewColumnKeys())
            ->values()
            ->all();

        return $validColumns;
    }

    public function participantOverviewColumns(): array
    {
        $settings = $this->rule_settings ?? [];
        $configured = self::normalizeParticipantOverviewColumns($settings['participant_overview_columns'] ?? null);

        if (!empty($configured)) {
            return $configured;
        }

        return $this->usesBopParticipantOverviewPreset()
            ? self::BOP_PARTICIPANT_OVERVIEW_COLUMNS
            : self::DEFAULT_PARTICIPANT_OVERVIEW_COLUMNS;
    }

    public function participantOverviewShowsMetrics(): bool
    {
        $settings = $this->rule_settings ?? [];

        if (array_key_exists('participant_overview_show_metrics', $settings)) {
            return (bool) $settings['participant_overview_show_metrics'];
        }

        return !$this->usesBopParticipantOverviewPreset();
    }

    public function usesBopParticipantOverviewPreset(): bool
    {
        $name = mb_strtoupper((string) $this->name);

        return str_contains($name, 'BOP')
            || str_contains($name, 'BERUFSORIENTIERUNG');
    }


    public function scopeAktiv($query)
    {
        return $query->where('aktiv', 1);
    }

    public function projectType()
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }


    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class, 'abteilung_id', 'id');
    }
    public function projektHasAnsprechpartner()
    {
        return $this->hasMany(ProjektHasAnsprechpartner::class, 'projekt_id', 'id');
    }

    public function projektHasPartner()
    {
        return $this->hasMany(ProjektHasPartner::class, 'projekt_id', 'id');
    }

    /* public function ansprechpartner()
    {
        return $this->hasManyThrough(
            Personen::class, // Ziel: Personen
            PartnerHasPartnerschaftstypen::class, // Pivot/Intermediate
            'projekt_id', // FK in Pivot auf Projekt
            'id', // PK in Person
            'id', // PK in Projekt
            'ansprechpartner_id', // FK in Pivot auf Person

        );
    }  */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(
            Partner::class,
            'projekt_has_partners',
            'projekt_id',
            'partner_id'
        );

        return $this->belongsToMany(
            PartnerHasPartnerschaftstypen::class,   // erste Zwischentabelle
            'projekt_has_ansprechpartners',          // Pivot-Tabelle Projekt ↔ Ansprechpartner
            'projekt_id',                            // FK auf Projekt
            'ansprechpartner_id'                     // FK auf PartnerHasPartnerschaftstypen
        )
        ->join('partners', 'partner_has_partnerschaftstypens.partner_id', '=', 'partners.id')
        ->select('partners.*');                     // gibt nur Partner zurück
    }



    public function kostenstellen()
    {
        return $this->belongsToMany(Kostenstelle::class, 'projekt_has_kostenstelles', 'projekt_id', 'kostenstelle_id')
            ->withPivot(['gueltig_von', 'gueltig_bis']);
    }
    public function teilnehmer()
    {
        return $this->belongsToMany(Personen::class, 'projekt_has_personens', 'projekt_id', 'personen_id');
    }

    public function participations()
    {
        return $this->hasMany(ProjektHasPersonen::class, 'projekt_id');
    }

    public function mitarbeiter()
    {
        return $this->belongsToMany(Personen::class, 'projekt_has_personens', 'projekt_id', 'personen_id')
            ->where('personens.typ', 'mitarbeiter')
            ->withPivot(['standort_id', 'status']);
    }

    public function standorte(){
        return $this->belongsToMany(Standort::class, 'projekt_has_personens', 'projekt_id', 'standort_id')
            ->withPivot(['personen_id']);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_has_projekts', 'projekt_id', 'user_id');
    }

    public function bereiche()
    {
        return $this->belongsToMany(Bereich::class, 'projekt_has_bereiches', 'projekt_id', 'bereich_id');
    }
    public function raeume()
    {
        return $this->belongsToMany(Raeume::class, 'projekt_has_raeumes', 'projekt_id', 'raum_id');
    }
    public function dokumente()
    {
        return $this->belongsToMany(Dokumente::class, 'projekt_has_dokumentes', 'projekt_id', 'dokument_id')
            ->withPivot(['gruppen_export', 'serienbrief', 'sort_order'])
            ->orderByPivot('sort_order')
            ->orderBy('dokumentes.name');
    }

    public function dokumentKategorien()
    {
        return $this->belongsToMany(DokumentKategorie::class, 'projekt_has_dokument_kategories', 'projekt_id', 'dokument_kategorie_id')
            ->orderBy('dokument_kategories.name');
    }

    public function potenzialanalyseUebungen()
    {
        return $this->hasMany(PotenzialanalyseUebung::class, 'projekt_id')
            ->orderBy('sort_order')
            ->orderBy('tag')
            ->orderBy('name');
    }

    public function intakeChecklistItems()
    {
        return $this->hasMany(ProjectIntakeChecklistItem::class, 'project_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function completionChecklistItems()
    {
        return $this->hasMany(ProjectCompletionChecklistItem::class, 'project_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model')->orderBy('antragsdatum', 'desc');
    }



}
