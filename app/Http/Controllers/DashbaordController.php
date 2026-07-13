<?php
namespace App\Http\Controllers;

use App\Models\{AppCalendarEvent, AppContact, AppFile, AppPopup, AppTask, DashboardPreference, Dienstwagen, Geraet, Personen, Projekt, Raeume, RoleDataAccessSetting, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DashbaordController extends Controller
{
    public const CARD_KEYS = ['projects', 'participants', 'rooms', 'vehicles', 'devices'];

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $locations = $user->standorte()->pluck('standorts.id');
        $can = fn (string ...$permissions) => collect($permissions)->contains(fn ($permission) => $user->can($permission));
        $cards = [
            'projects' => ['label' => 'Projekte', 'value' => $this->visibleProjects($user)->count(), 'visible' => $can('projekt.index', 'projekt.show'), 'scope' => $this->scopeLabel($user, 'Projekte')],
            'participants' => ['label' => 'Teilnehmer', 'value' => Personen::query()->aktiv()->teilnehmer()->visibleForUser($user)->count(), 'visible' => $can('teilnehmer.index', 'teilnehmer.projekt.index'), 'scope' => $this->scopeLabel($user, 'Teilnehmer')],
            'rooms' => ['label' => 'Räumlichkeiten', 'value' => $this->locationScopedCount(Raeume::query(), $locations), 'visible' => $can('raeumlichkeiten.index', 'räumlichkeiten.index'), 'scope' => 'Eigene Standorte'],
            'vehicles' => ['label' => 'Dienstwagen', 'value' => $this->locationScopedCount(Dienstwagen::query()->aktiv(), $locations), 'visible' => $can('dienstwagen.index', 'dienstwagen.buchung.index', 'dienstwagen.fahrtenbuch.index'), 'scope' => 'Eigene Standorte'],
            'devices' => ['label' => 'Geräte', 'value' => $this->locationScopedCount(Geraet::query(), $locations), 'visible' => $can('geraete.index', 'geraet.index', 'it.service.index'), 'scope' => 'Eigene Standorte'],
        ];
        $preference = DashboardPreference::firstOrCreate(['user_id' => $user->id], ['hidden_cards' => []]);
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
                'participants' => $cards['participants']['value'],
            ],
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate(['hidden_cards' => ['present', 'array'], 'hidden_cards.*' => ['string', Rule::in(self::CARD_KEYS)]]);
        DashboardPreference::updateOrCreate(['user_id' => $request->user()->id], ['hidden_cards' => array_values(array_unique($data['hidden_cards']))]);
        return back()->with('success', 'Dashboard wurde personalisiert.');
    }

    private function visibleProjects(User $user): Builder
    {
        $scope = RoleDataAccessSetting::scopeForUser($user, 'participant');
        $query = Projekt::query()->aktiv();
        if ($scope === 'all') return $query;
        if ($scope === 'department') {
            $ids = $user->projekte()->pluck('projekts.abteilung_id')->filter();
            return $ids->isEmpty() ? $query->whereRaw('1 = 0') : $query->whereIn('abteilung_id', $ids);
        }
        $ids = $user->projekte()->pluck('projekts.id');
        return $ids->isEmpty() ? $query->whereRaw('1 = 0') : $query->whereIn('id', $ids);
    }

    private function locationScopedCount(Builder $query, $locationIds): int { return $locationIds->isEmpty() ? 0 : $query->whereIn('standort_id', $locationIds)->count(); }
    private function scopeLabel(User $user, string $noun): string
    {
        return match (RoleDataAccessSetting::scopeForUser($user, 'participant')) {
            'all' => "Alle {$noun}", 'department' => "{$noun} meiner Abteilung", 'own_locations' => "{$noun} meiner Standorte",
            'current_project_same_location' => "{$noun} im aktuellen Projekt und Standort", 'own_projects' => "{$noun} meiner Projekte", default => "Keine freigegebenen {$noun}",
        };
    }
}
