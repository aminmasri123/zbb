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
        'dashboard.preferences.update' => 'dashboard.index',
        'account-deletion-requests.store' => 'user.profil',
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
        'anwesenheit.store' => 'anwesenheit.manage',
        'anwesenheit.update' => 'anwesenheit.manage',
        'gruppeHasPersonen.destroy' => 'anwesenheit.destroy',
        'gruppe.bop.export.anwesenheitsliste' => 'anwesenheit.export',
        'export.anwesenheitslite_V1' => 'anwesenheit.export',
        'export.projekt.anwesenheit.periode' => 'anwesenheit.export',
        'index-anpassung-anwesenheitsdaten' => 'anwesenheit.abrechnung',
        'export.anwesenheitsdaten.schule.excel' => 'anwesenheit.abrechnung',
        'anwesenheitslisteVorBOTage' => 'anwesenheit.abrechnung',
        'export.anwesenheitsliste.rechnung' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.POBO.bibb.preview' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.POBO.bibb.draft.show' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.POBO.bibb.draft.store' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.POBO.bibb.draft.destroy' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.POBO.bibb.export.word' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.PA.digital.preview' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.PA.digital.draft.show' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.PA.digital.draft.store' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.PA.digital.draft.destroy' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.PA.digital.draft.clear' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.PA.export.word' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.BoTag1.export' => 'anwesenheit.abrechnung',
        'anwesenheitsliste.POBO.bibb.archive.folder' => 'anwesenheit.archiv',
        'anwesenheitsliste.POBO.bibb.pdf.store' => 'anwesenheit.archiv',
        'anwesenheitsliste.PA.digital.archive.folder' => 'anwesenheit.archiv',
        'anwesenheitsliste.PA.digital.pdf.store' => 'anwesenheit.archiv',
        'bereichsauswahl.index' => [
            'bereichsauswahl.index',
            'bereichsauswahl.store',
            'bereichsauswahl.update',
            'bereichsauswahl.planning',
        ],
        'bereichsauswahl.setting.update' => 'bereichsauswahl.planning',
        'bereichsauswahl.bop.radio.update' => [
            'bereichsauswahl.store',
            'bereichsauswahl.update',
        ],
        'einteilung.show' => [
            'einteilung.index',
            'einteilung.store',
            'einteilung.update',
            'einteilung.destroy',
            'einteilung.export',
            'einteilung.planning',
        ],
        'einteilung.create' => 'einteilung.store',
        'einteilung.parameter.update' => 'einteilung.planning',
        'einteilung.runden.switch' => 'einteilung.planning',
        'gruppen.generieren' => 'einteilung.planning',
        'einteilung.export.excel' => 'einteilung.export',
        'hausordnung.export.schule.pdf' => 'dokumente.schule.export',
        'export.auswertungsbogenPA.schule.pdf' => 'dokumente.schule.export',
        'export.auswertungsbogenPA.roland.schule.pdf' => 'dokumente.schule.export',
        'export.zertifikat.schule.pobo' => 'dokumente.schule.export',
        'export.zertifikat.schule.pobo.pdf' => 'dokumente.schule.export',
        'export.auswertungBO.schule.pdf' => 'dokumente.schule.export',
        'auswertungPoboModal' => 'dokumente.schule.export',
        'export.teilnehmerliste.schule.excel' => 'teilnehmer.liste.export',
        'export.elterneinverstaendniserklaerung.schule' => 'dokumente.ansprechpartner.manage',
        'export.auswertungBO.schule.pdf.tofolder' => 'dokumente.ansprechpartner.manage',
        'export.auswertungPA.schule.pdf.tofolder' => 'dokumente.ansprechpartner.manage',
        'alleTeilnehmer.folder.create' => 'dokumente.ansprechpartner.manage',
        'potenzialanalyse.projekt.uebungen.store' => 'projekt.update',
        'potenzialanalyse.projekt.uebungen.update' => 'projekt.update',
        'potenzialanalyse.projekt.uebungen.destroy' => 'projekt.update',
        'potenzialanalyse.projekt.kriterien.store' => 'projekt.update',
        'potenzialanalyse.projekt.kriterien.update' => 'projekt.update',
        'potenzialanalyse.projekt.kriterien.destroy' => 'projekt.update',
        'potenzialanalyse.gruppe.teilnehmer.update' => 'gruppe.update',
        'projekt.intake-checklist.update' => 'projekt.update',
        'teilnehmer.intake-checklist.update' => 'teilnehmer.update',
        'projekt.completion-checklist.update' => 'projekt.update',
        'teilnehmer.completion-checklist.update' => 'teilnehmer.update',
        'teilnehmer.completion-reports.submit' => 'teilnehmer.update',
        'teilnehmer.completion-reports.decide' => 'teilnehmer.update',
        'teilnehmer.completion-reports.export' => 'teilnehmer.update',
        'teilnehmer.praktikum.update' => 'teilnehmer.update',
        'teilnehmer.praktikum.destroy' => 'teilnehmer.update',
        'teilnehmer.tasks.store' => 'teilnehmer.update',
        'teilnehmer.tasks.update' => 'teilnehmer.update',
        'teilnehmer.tasks.destroy' => 'teilnehmer.update',
        'teilnehmer.portal.invite' => 'teilnehmer.update',
        'projekt.portal-features.update' => 'projekt.update',
        'teilnehmer.applications.update' => 'teilnehmer.update',
        'projekt.courses.index' => 'projekt.update',
        'projekt.courses.store' => 'projekt.update',
        'projekt.courses.update' => 'projekt.update',
        'projekt.courses.lessons.store' => 'projekt.update',
        'projekt.courses.lessons.update' => 'projekt.update',
        'projekt.courses.enroll' => 'projekt.update',
        'projekt.courses.materials.store' => 'projekt.update',
        'projekt.courses.materials.update' => 'projekt.update',
        'projekt.courses.materials.download' => 'projekt.update',
        'projekt.courses.assignments.store' => 'projekt.update',
        'projekt.courses.assignments.update' => 'projekt.update',
        'projekt.courses.submissions.review' => 'projekt.update',
        'projekt.courses.submissions.download' => 'projekt.update',
        'projekt.courses.quizzes.store' => 'projekt.update',
        'projekt.courses.quizzes.update' => 'projekt.update',
        'projekt.courses.quizzes.questions.store' => 'projekt.update',
        'projekt.courses.quizzes.questions.update' => 'projekt.update',
        'projekt.courses.sessions.store' => 'projekt.update',
        'projekt.courses.sessions.update' => 'projekt.update',
        'projekt.courses.sessions.attendance' => 'projekt.update',
        'teilnehmer.attendance.corrections.resolve' => 'teilnehmer.update',
        'teilnehmer.portal-documents.store' => 'teilnehmer.update',
        'teilnehmer.portal-documents.review' => 'teilnehmer.update',
        'teilnehmer.portal-documents.download' => 'teilnehmer.update',
        'teilnehmer.messages.store' => 'teilnehmer.update',
        'teilnehmer.messages.read' => 'teilnehmer.update',
        'projekt.consents.index' => 'projekt.update',
        'projekt.consents.store' => 'projekt.update',
        'projekt.consents.revise' => 'projekt.update',
        'projekt.consents.active' => 'projekt.update',
        'teilnehmer.data-requests.resolve' => ['teilnehmer.data-request.manage', 'teilnehmer.update'],
        'teilnehmer.recommendations.store' => 'teilnehmer.update',
        'teilnehmer.applications.documents.sync' => 'teilnehmer.update',
        'teilnehmer.applications.package.approve' => 'teilnehmer.update',
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
