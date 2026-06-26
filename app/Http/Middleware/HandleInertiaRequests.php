<?php

namespace App\Http\Middleware;

use App\Models\AppPopup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
}
