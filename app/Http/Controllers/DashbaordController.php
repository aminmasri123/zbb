<?php

namespace App\Http\Controllers;

use App\Models\{AppCalendarEvent, AppContact, AppFile, AppPopup, AppTask, DashboardPreference, Dienstwagen, Geraet, Gruppe, Personen, Projekt, Raeume, RoleDataAccessSetting, User};
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DashbaordController extends Controller
{
    public const CARD_KEYS = [
        'projects',
        'participants',
        'rooms',
        'vehicles',
        'devices',
        'partners',
        'groups',
        'recent_participants',
    ];

    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $can = fn (string ...$permissions) => collect($permissions)
            ->contains(fn (string $permission) => $user->can($permission));
        $activeProject = $this->activeProjectContext->currentAvailableFor($user);
        $cards = [];

        $canViewProjects = $can('projekt.index', 'projekt.show');
        $canViewParticipants = $can('teilnehmer.index', 'teilnehmer.projekt.index');
        $canViewRooms = $can('raeumlichkeiten.index');
        $canViewVehicles = $can('dienstwagen.index', 'dienstwagen.buchungen.index', 'dienstwagen.fahrtenbuch.index');
        $canViewDevices = $can('geraet.index', 'it.service.index');
        $canViewPartners = $can('kooperationspartner.index');
        $canViewGroups = $can('gruppe.index');

        if ($canViewProjects) {
            $cards['projects'] = [
                'label' => 'Projekte',
                'type' => 'stat',
                'value' => $this->visibleProjects($user)->count(),
                'scope' => $this->scopeLabel($user, 'Projekte'),
            ];
        }

        $participantCount = 0;
        if ($canViewParticipants) {
            $participantQuery = Personen::query()->aktiv()->teilnehmer()->visibleForUser($user);
            $participantCount = (clone $participantQuery)->count();

            $cards['participants'] = [
                'label' => 'Teilnehmer',
                'type' => 'stat',
                'value' => $participantCount,
                'scope' => $this->scopeLabel($user, 'Teilnehmer'),
            ];
            $cards['recent_participants'] = [
                'label' => 'Letzte Teilnehmer',
                'type' => 'list',
                'scope' => 'Maximal 50 zuletzt angelegte Teilnehmer',
                'can_open' => $can('teilnehmer.update'),
                'items' => (clone $participantQuery)
                    ->orderByDesc('personens.created_at')
                    ->orderByDesc('personens.id')
                    ->limit(50)
                    ->get(['personens.id', 'personens.vorname', 'personens.nachname'])
                    ->map(fn (Personen $person) => [
                        'id' => $person->id,
                        'label' => trim("{$person->vorname} {$person->nachname}"),
                    ])
                    ->values(),
            ];
        }

        $locations = ($canViewRooms || $canViewVehicles || $canViewDevices)
            ? $user->standorte()->pluck('standorts.id')
            : collect();

        if ($canViewRooms) {
            $cards['rooms'] = [
                'label' => 'Räumlichkeiten',
                'type' => 'stat',
                'value' => $this->locationScopedCount(Raeume::query(), $locations),
                'scope' => 'Eigene Standorte',
            ];
        }
        if ($canViewVehicles) {
            $cards['vehicles'] = [
                'label' => 'Dienstwagen',
                'type' => 'stat',
                'value' => $this->locationScopedCount(Dienstwagen::query()->aktiv(), $locations),
                'scope' => 'Eigene Standorte',
            ];
        }
        if ($canViewDevices) {
            $cards['devices'] = [
                'label' => 'Geräte',
                'type' => 'stat',
                'value' => $this->locationScopedCount(Geraet::query(), $locations),
                'scope' => 'Eigene Standorte',
            ];
        }

        if ($canViewPartners) {
            $cards['partners'] = [
                'label' => 'Partner / Schulen',
                'type' => 'list',
                'scope' => $activeProject ? "Partner in {$activeProject->name}" : 'Kein aktives Projekt',
                'can_open' => $canViewGroups,
                'items' => $activeProject
                    ? $activeProject->partners()
                        ->orderBy('partners.name')
                        ->get(['partners.id', 'partners.name'])
                        ->map(fn ($partner) => ['id' => $partner->id, 'label' => $partner->name])
                        ->values()
                    : [],
            ];
        }

        if ($canViewGroups) {
            $canSeeAllGroups = $can('gruppe.view.all', 'projekt.mitarbeiter.view.all');
            $groupQuery = Gruppe::query()
                ->with(['bereich:id,name', 'betreuer:id,vorname,nachname'])
                ->when($activeProject, fn ($query) => $query->where('projekt_id', $activeProject->id))
                ->when(! $activeProject, fn ($query) => $query->whereRaw('1 = 0'))
                ->when(! $canSeeAllGroups, fn ($query) => $query->where('personen_id', $user->person_id))
                ->orderByDesc('anfangsdatum')
                ->orderByDesc('startzeit')
                ->orderByDesc('id');

            $cards['groups'] = [
                'label' => $canSeeAllGroups ? 'Gruppen' : 'Meine Gruppen',
                'type' => 'list',
                'scope' => $activeProject ? "Gruppen in {$activeProject->name}" : 'Kein aktives Projekt',
                'can_open' => $can('gruppeHasTeilnehmer.show'),
                'items' => $groupQuery
                    ->get(['id', 'personen_id', 'bereich_id', 'anfangsdatum', 'startzeit'])
                    ->map(function (Gruppe $group) {
                        $instructor = trim(($group->betreuer?->vorname ?? '').' '.($group->betreuer?->nachname ?? ''));
                        $date = $group->anfangsdatum ? date('d.m.Y', strtotime($group->anfangsdatum)) : null;

                        return [
                            'id' => $group->id,
                            'label' => $group->bereich?->name ?: "Gruppe #{$group->id}",
                            'meta' => collect([$instructor, $date])->filter()->join(' · '),
                        ];
                    })
                    ->values(),
            ];
        }

        $preference = DashboardPreference::firstOrCreate(
            ['user_id' => $user->id],
            ['hidden_cards' => []],
        );

        return Inertia::render('Dashboard', [
            'dashboardCards' => $cards,
            'hiddenCards' => array_values(array_intersect($preference->hidden_cards ?? [], self::CARD_KEYS)),
            'roleLabel' => $user->getRoleNames()->join(', '),
            'apps' => [
                'events' => $can('apps.calendar') ? AppCalendarEvent::count() : 0,
                'contacts' => $can('apps.contacts') ? AppContact::count() : 0,
                'files' => $can('apps.files') ? AppFile::where('type', 'file')->count() : 0,
                'tasks' => $can('apps.tasks') ? AppTask::where('status', '!=', 'done')->count() : 0,
                'popups' => $can('apps.popups') ? AppPopup::where('active', true)->count() : 0,
                'participants' => $participantCount,
            ],
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'hidden_cards' => ['present', 'array'],
            'hidden_cards.*' => ['string', Rule::in(self::CARD_KEYS)],
        ]);

        DashboardPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['hidden_cards' => array_values(array_unique($data['hidden_cards']))],
        );

        return back()->with('success', 'Dashboard wurde personalisiert.');
    }

    private function visibleProjects(User $user): Builder
    {
        $scope = RoleDataAccessSetting::scopeForUser($user, 'participant');
        $query = Projekt::query()->aktiv();

        if ($scope === 'all') {
            return $query;
        }
        if ($scope === 'department') {
            $ids = $user->projekte()->pluck('projekts.abteilung_id')->filter();

            return $ids->isEmpty() ? $query->whereRaw('1 = 0') : $query->whereIn('abteilung_id', $ids);
        }

        $ids = $user->projekte()->pluck('projekts.id');

        return $ids->isEmpty() ? $query->whereRaw('1 = 0') : $query->whereIn('id', $ids);
    }

    private function locationScopedCount(Builder $query, $locationIds): int
    {
        return $locationIds->isEmpty() ? 0 : $query->whereIn('standort_id', $locationIds)->count();
    }

    private function scopeLabel(User $user, string $noun): string
    {
        return match (RoleDataAccessSetting::scopeForUser($user, 'participant')) {
            'all' => "Alle {$noun}",
            'department' => "{$noun} meiner Abteilung",
            'own_locations' => "{$noun} meiner Standorte",
            'current_project_same_location' => "{$noun} im aktuellen Projekt und Standort",
            'own_projects' => "{$noun} meiner Projekte",
            default => "Keine freigegebenen {$noun}",
        };
    }
}
