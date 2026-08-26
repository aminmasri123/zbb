<?php

namespace App\Http\Middleware;

use App\Models\AppPopup;
use App\Models\AccountDeletionRequest;
use App\Models\Personen;
use App\Models\Projekt;
use App\Services\Modules\ModuleStateResolver;
use App\Services\Participants\ParticipantReminderService;
use App\Services\Projects\ActiveProjectContext;
use App\Models\ProjektHasPersonen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
                    && $request->user()->can('notifications.readAll')
                    ? $request->user()->unreadNotifications()->take(5)->get()
                    : [],

                'unreadCount' => $request->user()
                    && $request->user()->can('notifications.readAll')
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
            ],




            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],

            'appPopups' => fn () => $request->user()?->can('apps.popups')
                ? $this->visiblePopupsFor($request)->take(3)->get(['id', 'title', 'message', 'level'])
                : [],

            'currentProjekt' => fn () => $this->currentProjektFor($request),

            'accountDeletionRequest' => fn () => $this->accountDeletionRequestFor($request),

            'enabledModules' => fn () => $request->user()
                ? app(ModuleStateResolver::class)->availableStates()
                : [],

            'canManageProfile' => fn () => (bool) $request->user()?->can('user.profil'),

            'canManageNotifications' => fn () => (bool) $request->user()?->can('notifications.readAll'),

            'canUseStaffChat' => fn () => (bool) $request->user()?->hasStoredPermission('chat.use'),

            'staffChatUnreadCount' => fn () => $this->staffChatUnreadCount($request),
            'staffParticipantChatUnreadCount' => fn () => $this->staffParticipantChatUnreadCount($request),

            'participantPortalNavigation' => fn () => $this->participantPortalNavigation($request),
            'participantPortalUnreadCount' => fn () => $this->participantPortalUnreadCount($request),

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
            ->where('status', 'aktiv')
            ->with('projekt:id,feature_settings,portal_feature_settings')->get()
            ->filter(fn ($participation) => $participation->projekt?->featureEnabled('participant_portal'))
            ->map(fn ($participation) => $participation->projekt->portalFeatureSettings());

        $enabled = fn (string $key) => $features->contains(fn (array $settings) => (bool) ($settings[$key] ?? false));

        return [
            'profile' => $enabled('profile'),
            'attendance' => $enabled('attendance_self_service') || $enabled('tasks_and_appointments'),
            'attendance_self_service' => $enabled('attendance_self_service'),
            'tasks_and_appointments' => $enabled('tasks_and_appointments'),
            'jobs' => $enabled('job_search') || $enabled('application_management'),
            'applications' => $enabled('application_management'),
            'learning' => $enabled('learning'),
            'messaging' => $enabled('messaging'),
            'consents' => $enabled('consents_and_approvals'),
        ];
    }

    private function participantPortalUnreadCount(Request $request): int
    {
        $user = $request->user();
        if (! $user || $user->person?->typ !== 'teilnehmer') return 0;

        app(ParticipantReminderService::class)->syncInAppNotifications($user);

        return $user->unreadNotifications()->count();
    }

    private function staffChatUnreadCount(Request $request): int
    {
        $user = $request->user();
        if (! $user || ! $user->hasStoredPermission('chat.use') || ! Schema::hasTable('staff_messages')) {
            return 0;
        }

        $internalUnread = DB::table('staff_messages as messages')
            ->join('staff_conversation_members as membership', function ($join) use ($user) {
                $join->on('membership.conversation_id', '=', 'messages.conversation_id')
                    ->where('membership.user_id', '=', $user->id);
            })
            ->where(function ($query) use ($user) {
                $query->whereNull('messages.sender_user_id')
                    ->orWhere('messages.sender_user_id', '!=', $user->id);
            })
            ->whereRaw('messages.created_at > COALESCE(membership.last_read_at, membership.joined_at)')
            ->count();

        return $internalUnread + $this->staffParticipantChatUnreadCount($request);
    }

    private function staffParticipantChatUnreadCount(Request $request): int
    {
        $user = $request->user();
        if (! $user
            || ! $user->hasStoredPermission('chat.use')
            || ! $user->can('teilnehmer.update')
            || ! $user->current_team_id
            || ! Schema::hasTable('participant_portal_staff_reads')
            || ! app(ModuleStateResolver::class)->enabled('participant_portal')) {
            return 0;
        }

        $project = Projekt::query()->find($user->current_team_id);
        if (! $project
            || ! $project->featureEnabled('participant_portal')
            || ! $project->portalFeatureEnabled('messaging')) {
            return 0;
        }

        $visibleParticipantIds = Personen::query()
            ->teilnehmer()
            ->visibleForUser($user)
            ->pluck('id');

        return DB::table('participant_portal_messages as portal_messages')
            ->join('projekt_has_personens as participation', 'participation.id', '=', 'portal_messages.project_person_id')
            ->leftJoin('participant_portal_staff_reads as portal_reads', function ($join) use ($user) {
                $join->on('portal_reads.project_person_id', '=', 'participation.id')
                    ->where('portal_reads.user_id', '=', $user->id);
            })
            ->where('participation.projekt_id', $project->id)
            ->where('participation.status', 'aktiv')
            ->whereIn('participation.personen_id', $visibleParticipantIds)
            ->where('portal_messages.sender_kind', 'participant')
            ->whereRaw("portal_messages.created_at > COALESCE(portal_reads.last_read_at, '1970-01-01 00:00:00')")
            ->count();
    }
}
