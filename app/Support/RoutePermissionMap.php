<?php

namespace App\Support;

class RoutePermissionMap
{
    /**
     * Route names that intentionally use a different permission name.
     *
     * @var array<string, string|array<int, string>>
     */
    private const OVERRIDES = [
        'dashboard' => 'dashboard.index',
        'user.theme.update' => 'dashboard.index',
        'bereich.indexAjaxFresh' => 'bereich.index',
        'abteilung.indexAjaxFresh' => 'abteilung.index',
        'projekt.indexAjaxFresh' => 'projekt.index',
        'dashboard.partner.index' => 'kooperationspartner.index',
        'partner.index' => 'kooperationspartner.index',
        'partner.indexAjaxFresh' => 'kooperationspartner.index',
        'partner.store' => 'kooperationspartner.store',
        'partner.update' => 'kooperationspartner.update',
        'partner.destroy' => 'kooperationspartner.destroy',
        'geraet.edit' => 'geraet.update',
        'getGeraeteID' => 'geraet.index',
        'it-service.index' => [
            'it.service.index',
            'it.ticket.store',
            'it.ticket.update',
            'it.geraet.update',
            'geraet.index',
            'geraet.update',
        ],
        'it-service.tickets.store' => ['it.ticket.store', 'it.ticket.update'],
        'it-service.tickets.update' => 'it.ticket.update',
        'it-service.tickets.destroy' => 'it.ticket.destroy',
        'it-service.geraete.store' => ['it.geraet.store', 'geraet.store'],
        'it-service.geraete.update' => ['it.geraet.update', 'geraet.update'],
        'it-service.geraete.destroy' => ['it.geraet.destroy', 'geraet.destroy', 'geraet.delete'],
        'raeumlichkeiten.buchung.store' => ['raeumlichkeiten.buchung.store', 'raeumlichkeiten.update'],
        'raeumlichkeiten.buchung.update' => ['raeumlichkeiten.buchung.update', 'raeumlichkeiten.update'],
        'raeumlichkeiten.buchung.destroy' => [
            'raeumlichkeiten.buchung.destroy',
            'raeumlichkeiten.buchung.update',
            'raeumlichkeiten.update',
        ],
        'responsive' => 'dashboard.index',
        'notifications.index' => 'notifications.readAll',
        'notifications.read' => 'notifications.readAll',
        'notifications.unread' => 'notifications.readAll',
        'notifications.destroy' => 'notifications.readAll',
        'anwesenheitsliste.PA.digital.draft.clear' => 'anwesenheitsliste.PA.digital.draft.destroy',
    ];

    /**
     * @return array<int, string>
     */
    public static function permissionsFor(?string $routeName): array
    {
        if (! $routeName) {
            return [];
        }

        $permissions = self::OVERRIDES[$routeName] ?? $routeName;

        return array_values(array_unique(array_filter((array) $permissions)));
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function overrides(): array
    {
        return self::OVERRIDES;
    }
}
