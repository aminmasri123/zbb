<?php

namespace App\Http\Middleware;

use App\Models\AppPopup;
use App\Models\AccountDeletionRequest;
use App\Services\Modules\ModuleStateResolver;
use App\Services\Projects\ActiveProjectContext;
use App\Models\ProjektHasPersonen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [

            'notify' => [
                'user' => $request->user()
                    ? [
                        'id'   => $request->user()->id,
                        'name' => $request->user()->name,
                      ]
                    : null,

                // WICHTIG: ->take(5)->get() damit wirklich ein Array kommt
                'notifications' => $request->user()
                    ? $request->user()->unreadNotifications()->take(5)->get()
                    : [],
            ],




            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],

            'appPopups' => fn () => $request->user()
                ? $this->visiblePopupsFor($request)->take(3)->get(['id', 'title', 'message', 'level'])
                : [],

            'currentProjekt' => fn () => $this->currentProjektFor($request),

            'accountDeletionRequest' => fn () => $this->accountDeletionRequestFor($request),

            'enabledModules' => fn () => $request->user()
                ? app(ModuleStateResolver::class)->availableStates()
                : [],

            'participantPortalNavigation' => fn () => $this->participantPortalNavigation($request),

           /*  'auth' => [
                'user' => fn () => $request->user()
                    ? $request->user()->load('projekte') // Relation anhängen
                    : null,
            ], */
        ]);





    }

    private function visiblePopupsFor(Request $request)
    {
        $user = $request->user();
        $personId = $user->person_id;
        $teamId = $user->current_team_id;
        $now = now();

        return AppPopup::query()
            ->where('active', true)
            ->where(function (Builder $date) use ($now) {
                $date->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $date) use ($now) {
                $date->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function (Builder $q) use ($user, $personId, $teamId) {
                $q->where('owner_user_id', $user->id)
                    ->orWhere('visibility', 'all');

                if ($teamId) {
                    $q->orWhere(function (Builder $team) use ($teamId) {
                        $team->where('visibility', 'team')->where('team_id', $teamId);
                    })->orWhere(function (Builder $project) use ($teamId) {
                        $project->where('visibility', 'project')->where('project_id', $teamId);
                    });
                }

                $q->orWhereHas('shares', function (Builder $share) use ($personId, $user) {
                    $share->where(function (Builder $target) use ($personId, $user) {
                        if ($personId) {
                            $target->where('person_id', $personId);
                        }

                        $target->orWhere('email', $user->email);
                    });
                });
            })
            ->orderByDesc('created_at');
    }

    private function currentProjektFor(Request $request): ?array
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $context = app(ActiveProjectContext::class);

        return $context->payload($context->currentFor($user));
    }

    private function accountDeletionRequestFor(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || ! Schema::hasTable('account_deletion_requests')) {
            return null;
        }

        $deletionRequest = AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->latest()
            ->first();

        if (! $deletionRequest) {
            return null;
        }

        return [
            'id' => $deletionRequest->id,
            'status' => $deletionRequest->status,
            'created_at' => $deletionRequest->created_at?->toISOString(),
        ];
    }

    private function participantPortalNavigation(Request $request): ?array
    {
        $user = $request->user();
        if (!$user?->person_id || $user->person?->typ !== 'teilnehmer') return null;

        $features = ProjektHasPersonen::query()->where('personen_id', $user->person_id)
            ->with('projekt:id,portal_feature_settings')->get()
            ->map(fn ($participation) => $participation->projekt->portalFeatureSettings());

        $enabled = fn (string $key) => $features->contains(fn (array $settings) => (bool) ($settings[$key] ?? false));

        return [
            'profile' => $enabled('profile'),
            'attendance' => $enabled('attendance_self_service') || $enabled('tasks_and_appointments'),
            'jobs' => $enabled('job_search') || $enabled('application_management'),
            'learning' => $enabled('learning'),
            'messaging' => $enabled('messaging'),
            'consents' => $enabled('consents_and_approvals'),
        ];
    }
}
