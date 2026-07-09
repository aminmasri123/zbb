<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

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

    public const CORE_EVENTS = [
        'user.created' => 'Neuer User',
        'projekt.created' => 'Neues Projekt',
        'teilnehmer.created' => 'Neuer Teilnehmer',
        'dienstwagen.created' => 'Neuer Dienstwagen',
        'raeumlichkeiten.created' => 'Neuer Raum',
        'raeumlichkeiten.meldung.created' => 'Neue Raummeldung',
        'geraet.created' => 'Neues Gerät',
        'it.ticket.created' => 'Neues IT-Ticket',
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

    private const IGNORED_ROUTE_EVENT_PREFIXES = [
        'notification-rules.',
        'notifications.',
        'berechtigung.',
        'rolle.',
        'api-tokens.',
        'two-factor.',
        'password.',
        'current-user.',
        'current-user-photo.',
        'other-browser-sessions.',
        'user-password.',
        'user-profile-information.',
    ];

    private const IGNORED_ROUTE_EVENTS = [
        'user.store',
        'projekt.store',
        'teilnehmer.store',
        'dienstwagen.store',
        'raeumlichkeiten.store',
        'raeumlichkeiten.meldung.store',
        'geraet.store',
        'it-service.tickets.store',
        'materialanforderung.store',
        'materialanforderung.update',
        'klassenbuch.woche.submit',
        'set-locale',
        'logout',
        'login',
        'register',
        'verification.send',
    ];

    private const MODULE_LABELS = [
        'abteilung' => 'Abteilung',
        'abschluss' => 'Abschluss',
        'adresse' => 'Adresse',
        'anwesenheit' => 'Anwesenheit',
        'anwesenheitsliste.PA.digital.archive' => 'PA Digital Archiv',
        'anwesenheitsliste.PA.digital.draft' => 'PA Digital Entwurf',
        'anwesenheitsliste.PA.digital.pdf' => 'PA Digital PDF',
        'anwesenheitsliste.PA.export' => 'PA Export',
        'anwesenheitsliste.POBO.bibb.archive' => 'POBO BIBB Archiv',
        'anwesenheitsliste.POBO.bibb.draft' => 'POBO BIBB Entwurf',
        'anwesenheitsliste.POBO.bibb.export' => 'POBO BIBB Export',
        'anwesenheitsliste.POBO.bibb.pdf' => 'POBO BIBB PDF',
        'anwesenheitsliste' => 'Anwesenheitsliste',
        'apps' => 'App',
        'apps.calendar.calendars' => 'Kalenderverwaltung',
        'apps.calendar.import' => 'Kalenderimport',
        'apps.calendar.styles' => 'Kalenderfarbe',
        'apps.calendar' => 'Kalendertermin',
        'apps.contacts' => 'Kontakt',
        'apps.files.folder' => 'Dateimanager-Ordner',
        'apps.files' => 'Dateimanager-Datei',
        'apps.popups' => 'Popup',
        'apps.tasks.workflows' => 'Task-Workflow',
        'apps.tasks' => 'Task',
        'bank' => 'Bankdaten',
        'bereichsauswahl.bop.radio' => 'Bereichsauswahl BOP Radio',
        'bereichsauswahl.setting' => 'Bereichsauswahl Einstellung',
        'bereich' => 'Bereich',
        'brief' => 'Brief',
        'dienstwagen.buchungen' => 'Dienstwagenbuchung',
        'dienstwagen.drivers' => 'Dienstwagenfahrer',
        'dienstwagen.fahrtenbuch.report' => 'Fahrtenbuchbericht',
        'dienstwagen.fahrtenbuch' => 'Fahrtenbuch',
        'dienstwagen.kosten' => 'Dienstwagenkosten',
        'dienstwagen.meldungen' => 'Dienstwagenmeldung',
        'dienstwagen.reports' => 'Dienstwagenbericht',
        'dienstwagen.verlauf' => 'Dienstwagenverlauf',
        'dienstwagen.wartung' => 'Dienstwagenwartung',
        'dienstwagen' => 'Dienstwagen',
        'dokumente.kategorien' => 'Dokumentenkategorie',
        'dokumente.projekt-kategorien' => 'Projektdokumentenkategorie',
        'dokumente' => 'Dokumente',
        'einteilung.parameter' => 'Einteilungsparameter',
        'einteilung' => 'Einteilung',
        'fahrtarten' => 'Fahrtart',
        'fahrtkosten' => 'Fahrtkosten',
        'fahrtkostenAbrechnung' => 'Fahrtkostenabrechnung',
        'geraet.ausgabe' => 'Geräteausgabe',
        'geraet.rueckgabe' => 'Geräterückgabe',
        'geraet' => 'Gerät',
        'it-service.tickets' => 'IT-Ticket',
        'it-service.geraete' => 'IT-Gerät',
        'it-service' => 'IT-Service',
        'it.ticket' => 'IT-Ticket',
        'it' => 'IT-Service',
        'gruppe' => 'Gruppe',
        'gruppeHasTeilnehmer' => 'Gruppen-Teilnehmer',
        'klassenbuch.eintrag' => 'Klassenbucheintrag',
        'klassenbuch.kommentar' => 'Klassenbuchkommentar',
        'klassenbuch' => 'Klassenbuch',
        'kontakt' => 'Kontakt',
        'kooperationspartner' => 'Kooperationspartner',
        'kostenstelle' => 'Kostenstelle',
        'materialanforderung' => 'Materialanforderung',
        'notizen' => 'Notiz',
        'person' => 'Person',
        'personal' => 'Personal',
        'printing' => 'Printing',
        'projekt.dokumente' => 'Projektdokumente',
        'projekt' => 'Projekt',
        'projekthasteilnehmer.luv' => 'LUV Teilnehmer-Projekt-Zuordnung',
        'projekthasteilnehmer' => 'Teilnehmer-Projekt-Zuordnung',
        'raeumlichkeiten.meldung' => 'Raummeldung',
        'raeumlichkeiten' => 'Räumlichkeiten',
        'schule' => 'Schule',
        'standort' => 'Standort',
        'teilnehmer' => 'Teilnehmer',
        'user.theme' => 'User Theme',
        'user' => 'User',
    ];

    private const ACTION_LABELS = [
        'store' => 'angelegt',
        'create' => 'erstellt',
        'update' => 'aktualisiert',
        'destroy' => 'gelöscht',
        'delete' => 'gelöscht',
        'bulkDestroy' => 'mehrfach gelöscht',
        'import' => 'importiert',
        'export' => 'exportiert',
        'download' => 'heruntergeladen',
        'mail' => 'per E-Mail versendet',
        'share' => 'freigegeben',
        'submit' => 'eingereicht',
        'review' => 'geprüft',
        'switch' => 'gewechselt',
        'genehmigen' => 'genehmigt',
        'move' => 'verschoben',
        'copy' => 'kopiert',
        'apply' => 'angewendet',
        'add' => 'hinzugefügt',
        'confirm' => 'bestätigt',
        'folder' => 'Ordner verarbeitet',
        'preview' => 'Vorschau erstellt',
        'show' => 'angezeigt',
        'word' => 'als Word exportiert',
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

    public static function events(): array
    {
        return self::CORE_EVENTS + self::routeEvents();
    }

    public static function routeEvents(): array
    {
        $events = collect(Route::getRoutes())
            ->mapWithKeys(function ($route) {
                $name = $route->getName();

                if (! $name || ! self::isRouteEventName($name)) {
                    return [];
                }

                $methods = $route->methods();

                if (! array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                    return [];
                }

                return [$name => self::routeEventLabel($name)];
            })
            ->filter();

        $labelCounts = collect(self::CORE_EVENTS)
            ->merge($events)
            ->countBy();

        return $events
            ->map(fn (string $label, string $eventKey) => $labelCounts[$label] > 1
                ? $label . ' (' . $eventKey . ')'
                : $label)
            ->sort()
            ->all();
    }

    public static function isConfiguredEvent(string $eventKey): bool
    {
        return array_key_exists($eventKey, self::events());
    }

    public static function labelForEvent(string $eventKey): string
    {
        return self::events()[$eventKey] ?? $eventKey;
    }

    public static function moduleLabelForEvent(string $eventKey): string
    {
        $segments = explode('.', $eventKey);
        $prefix = $segments[0] ?? $eventKey;

        for ($length = count($segments); $length > 0; $length--) {
            $candidate = implode('.', array_slice($segments, 0, $length));

            if (isset(self::MODULE_LABELS[$candidate])) {
                return self::MODULE_LABELS[$candidate];
            }
        }

        return self::MODULE_LABELS[$prefix] ?? ucfirst(str_replace(['-', '_'], ' ', $prefix));
    }

    private static function isRouteEventName(string $name): bool
    {
        if (in_array($name, self::IGNORED_ROUTE_EVENTS, true)) {
            return false;
        }

        foreach (self::IGNORED_ROUTE_EVENT_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private static function routeEventLabel(string $eventKey): string
    {
        $segments = explode('.', $eventKey);
        $module = self::moduleLabelForEvent($eventKey);
        $action = self::actionLabel($segments);

        return trim($module . ' ' . $action);
    }

    private static function actionLabel(array $segments): string
    {
        $last = end($segments) ?: '';

        if (isset(self::ACTION_LABELS[$last])) {
            return self::ACTION_LABELS[$last];
        }

        foreach (array_reverse($segments) as $segment) {
            if (isset(self::ACTION_LABELS[$segment])) {
                return self::ACTION_LABELS[$segment];
            }
        }

        return str_replace(['-', '_'], ' ', $last);
    }

    public static function defaultRules(): array
    {
        return [
            [
                'event_key' => 'user.created',
                'label' => self::CORE_EVENTS['user.created'],
                'target_type' => 'permission',
                'target_value' => 'benutzer.index',
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 1,
            ],
            [
                'event_key' => 'projekt.created',
                'label' => self::CORE_EVENTS['projekt.created'],
                'target_type' => 'permission',
                'target_value' => 'projekt.index',
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 2,
            ],
            [
                'event_key' => 'teilnehmer.created',
                'label' => self::CORE_EVENTS['teilnehmer.created'],
                'target_type' => 'permission',
                'target_value' => 'teilnehmer.index',
                'scope' => 'current_project',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 3,
            ],
            [
                'event_key' => 'dienstwagen.created',
                'label' => self::CORE_EVENTS['dienstwagen.created'],
                'target_type' => 'permission',
                'target_value' => 'dienstwagen.index',
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 4,
            ],
            [
                'event_key' => 'raeumlichkeiten.created',
                'label' => self::CORE_EVENTS['raeumlichkeiten.created'],
                'target_type' => 'permission',
                'target_value' => 'raeumlichkeiten.index',
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 5,
            ],
            [
                'event_key' => 'raeumlichkeiten.meldung.created',
                'label' => self::CORE_EVENTS['raeumlichkeiten.meldung.created'],
                'target_type' => 'permission',
                'target_value' => 'raeumlichkeiten.meldung.update',
                'scope' => 'current_project',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 6,
            ],
            [
                'event_key' => 'geraet.created',
                'label' => self::CORE_EVENTS['geraet.created'],
                'target_type' => 'permission',
                'target_value' => 'geraet.index',
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 7,
            ],
            [
                'event_key' => 'it.ticket.created',
                'label' => self::CORE_EVENTS['it.ticket.created'],
                'target_type' => 'permission',
                'target_value' => 'it.ticket.update',
                'scope' => 'none',
                'channels' => ['database'],
                'active' => true,
                'exclude_actor' => true,
                'sort_order' => 8,
            ],
            [
                'event_key' => 'materialanforderung.eingereicht',
                'label' => self::CORE_EVENTS['materialanforderung.eingereicht'],
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
                'label' => self::CORE_EVENTS['materialanforderung.sachlich_genehmigt'],
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
                'label' => self::CORE_EVENTS['materialanforderung.kaufmaennisch_genehmigt'],
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
                'label' => self::CORE_EVENTS['materialanforderung.zur_ueberarbeitung'],
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
                'label' => self::CORE_EVENTS['materialanforderung.stornieren'],
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
                'label' => self::CORE_EVENTS['materialanforderung.bestellt'],
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
                'label' => self::CORE_EVENTS['materialanforderung.geliefert'],
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
                'label' => self::CORE_EVENTS['materialanforderung.teilweise_geliefert'],
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
                'label' => self::CORE_EVENTS['klassenbuch.woche.zur_pruefung'],
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
