<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'label',
        'target_type',
        'target_value',
        'scope',
        'channels',
        'active',
        'exclude_actor',
        'sort_order',
    ];

    protected $casts = [
        'channels' => 'array',
        'active' => 'boolean',
        'exclude_actor' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const EVENTS = [
        'materialanforderung.eingereicht' => 'Materialanforderung eingereicht',
        'materialanforderung.sachlich_genehmigt' => 'Materialanforderung sachlich genehmigt',
        'materialanforderung.kaufmaennisch_genehmigt' => 'Materialanforderung kaufmaennisch genehmigt',
        'materialanforderung.zur_ueberarbeitung' => 'Materialanforderung zur Ueberarbeitung',
        'materialanforderung.stornieren' => 'Materialanforderung storniert',
        'materialanforderung.bestellt' => 'Materialanforderung bestellt',
        'materialanforderung.geliefert' => 'Materialanforderung geliefert',
        'materialanforderung.teilweise_geliefert' => 'Materialanforderung teilweise geliefert',
        'klassenbuch.woche.zur_pruefung' => 'Klassenbuch Woche zur Pruefung',
    ];

    public const TARGET_TYPES = [
        'permission' => 'Permission',
        'role' => 'Rolle',
        'creator' => 'Ersteller',
        'department_reviewers' => 'Abteilungsleitung und Assistenz',
    ];

    public const SCOPES = [
        'none' => 'Kein Scope',
        'current_project' => 'Aktuelles Projekt',
    ];

    public const CHANNELS = [
        'database' => 'In-App',
    ];

    public static function defaultRules(): array
    {
        return [
            [
                'event_key' => 'materialanforderung.eingereicht',
                'label' => self::EVENTS['materialanforderung.eingereicht'],
                'target_type' => 'permission',
                'target_value' => 'materialanforderung.sachlische_freigabe.index',
                'scope' => 'current_project',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 10,
            ],
            [
                'event_key' => 'materialanforderung.sachlich_genehmigt',
                'label' => self::EVENTS['materialanforderung.sachlich_genehmigt'],
                'target_type' => 'permission',
                'target_value' => 'materialanforderung.kaufmännische_freigabe.update',
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 20,
            ],
            [
                'event_key' => 'materialanforderung.kaufmaennisch_genehmigt',
                'label' => self::EVENTS['materialanforderung.kaufmaennisch_genehmigt'],
                'target_type' => 'permission',
                'target_value' => 'materialanforderung.bestellwesen.update',
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 30,
            ],
            [
                'event_key' => 'materialanforderung.zur_ueberarbeitung',
                'label' => self::EVENTS['materialanforderung.zur_ueberarbeitung'],
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 40,
            ],
            [
                'event_key' => 'materialanforderung.stornieren',
                'label' => self::EVENTS['materialanforderung.stornieren'],
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 50,
            ],
            [
                'event_key' => 'materialanforderung.bestellt',
                'label' => self::EVENTS['materialanforderung.bestellt'],
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 60,
            ],
            [
                'event_key' => 'materialanforderung.geliefert',
                'label' => self::EVENTS['materialanforderung.geliefert'],
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 70,
            ],
            [
                'event_key' => 'materialanforderung.teilweise_geliefert',
                'label' => self::EVENTS['materialanforderung.teilweise_geliefert'],
                'target_type' => 'creator',
                'target_value' => null,
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => false,
                'sort_order' => 80,
            ],
            [
                'event_key' => 'klassenbuch.woche.zur_pruefung',
                'label' => self::EVENTS['klassenbuch.woche.zur_pruefung'],
                'target_type' => 'department_reviewers',
                'target_value' => null,
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 90,
            ],
        ];
    }

    public static function ensureDefaultRules(): void
    {
        foreach (self::defaultRules() as $rule) {
            self::firstOrCreate(
                [
                    'event_key' => $rule['event_key'],
                    'target_type' => $rule['target_type'],
                    'target_value' => $rule['target_value'],
                ],
                $rule
            );
        }
    }
}
