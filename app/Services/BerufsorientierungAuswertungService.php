<?php

namespace App\Services;

use App\Models\Projekt;
use Illuminate\Support\Str;

class BerufsorientierungAuswertungService
{
    public const SCALE = [
        1 => 'Da geht noch was',
        2 => 'Ganz okay',
        3 => 'Gut',
        4 => 'Sehr gut',
        5 => 'Hervorragend',
    ];

    public const BOP_CRITERIA = [
        ['key' => 'einhaltung_der_regeln', 'label' => 'Einhaltung der Regeln'],
        ['key' => 'verständnis_des_arbeitsauftrages', 'label' => 'Verständnis des Arbeitsauftrages'],
        ['key' => 'bereitschaft_der_auftragsübernahme', 'label' => 'Bereitschaft zur Auftragsübernahme'],
        ['key' => 'selbständigkeit_in_der_durchführung_der_aufgabe', 'label' => 'Selbständigkeit in der Durchführung der Aufgabe'],
        ['key' => 'freude_an_der_arbeit', 'label' => 'Freude an der Arbeit'],
        ['key' => 'korrekter_umgang_mit_werkzeug_und_material', 'label' => 'Korrekter Umgang mit Werkzeug und Material'],
        ['key' => 'methodisches_herangehen_an_die_aufgabenerledigung', 'label' => 'Methodisches Herangehen an die Aufgabenerledigung'],
        ['key' => 'sorgfäligkeit_in_der_aufgabenerledigung', 'label' => 'Sorgfältigkeit in der Aufgabenerledigung'],
        ['key' => 'ordnung_am_arbeitsplatz', 'label' => 'Ordnung am Arbeitsplatz'],
        ['key' => 'soziale_kompetenzen', 'label' => 'Soziale Kompetenzen'],
        ['key' => 'einschätzung_der_befähigung_und_eignung_zur_berufsorientierung', 'label' => 'Einschätzung der Befähigung und Eignung zur Berufsorientierung'],
    ];

    public function config(Projekt $projekt): array
    {
        $config = $projekt->berufsorientierung_auswertung_config;
        $criteria = collect($config['criteria'] ?? ($projekt->usesBopParticipantOverviewPreset() ? self::BOP_CRITERIA : []))
            ->map(fn ($item, $index) => [
                'key' => Str::limit((string) ($item['key'] ?? Str::slug((string) ($item['label'] ?? ''), '_')), 191, ''),
                'label' => trim((string) ($item['label'] ?? '')),
                'description' => trim((string) ($item['description'] ?? '')),
                'required' => (bool) ($item['required'] ?? true),
                'sort_order' => (int) ($item['sort_order'] ?? $index),
            ])
            ->filter(fn ($item) => $item['key'] !== '' && $item['label'] !== '')
            ->sortBy('sort_order')->values()->all();

        return [
            'enabled' => (bool) ($config['enabled'] ?? $projekt->usesBopParticipantOverviewPreset()),
            'scale' => self::SCALE,
            'criteria' => $criteria,
        ];
    }
}
