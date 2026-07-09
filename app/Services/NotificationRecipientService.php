<?php

namespace App\Services;

use App\Models\Klassenbuch;
use App\Models\KlassenbuchWoche;
use App\Models\Materialanforderung;
use App\Models\NotificationRule;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationRecipientService
{
    public function forMaterialanforderung(Materialanforderung $anforderung, string $status, ?User $actor = null): Collection
    {
        $eventKey = 'materialanforderung.' . $status;

        return $this->configuredRecipients($eventKey, [
            'actor' => $actor,
            'anforderung' => $anforderung,
            'project_id' => $this->projectIdForMaterialanforderung($anforderung, $actor),
        ], fn () => match ($status) {
            'eingereicht' => $this->withoutActor(
                $this->usersWithPermissionInProject(
                    'materialanforderung.sachlische_freigabe.index',
                    $this->projectIdForMaterialanforderung($anforderung, $actor)
                ),
                $actor
            ),
            'sachlich_genehmigt' => $this->withoutActor(
                $this->usersWithPermission('materialanforderung.kaufmännische_freigabe.update'),
                $actor
            ),
            'kaufmaennisch_genehmigt' => $this->withoutActor(
                $this->usersWithPermission('materialanforderung.bestellwesen.update'),
                $actor
            ),
            'zur_ueberarbeitung',
            'stornieren',
            'bestellt',
            'geliefert',
            'teilweise_geliefert' => $this->creatorOfMaterialanforderung($anforderung),
            default => collect(),
        });
    }

    public function forKlassenbuchWocheZurPruefung(KlassenbuchWoche $woche, ?User $actor = null): Collection
    {
        $woche->loadMissing('klassenbuch.gruppe.projekt');

        return $this->configuredRecipients('klassenbuch.woche.zur_pruefung', [
            'actor' => $actor,
            'woche' => $woche,
            'klassenbuch' => $woche->klassenbuch,
            'project_id' => $woche->klassenbuch?->gruppe?->projekt_id,
        ], fn () => $this->withoutActor(
            $this->reviewersForKlassenbuch($woche->klassenbuch),
            $actor
        ));
    }

    public function forEvent(string $eventKey, array $context = [], ?Closure $fallback = null): Collection
    {
        if (Schema::hasTable('notification_rules')) {
            NotificationRule::ensureDefaultRules();
        }

        return $this->configuredRecipients(
            $eventKey,
            $context,
            $fallback ?: fn () => collect()
        );
    }

    private function configuredRecipients(string $eventKey, array $context, Closure $fallback): Collection
    {
        if (! Schema::hasTable('notification_rules')) {
            return $fallback();
        }

        $rules = NotificationRule::query()
            ->where('event_key', $eventKey)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return $fallback();
        }

        return $this->uniqueUsers(
            $rules
                ->filter(fn (NotificationRule $rule) => $rule->active)
                ->flatMap(fn (NotificationRule $rule) => $this->usersForRule($rule, $context))
        );
    }

    private function usersForRule(NotificationRule $rule, array $context): Collection
    {
        $users = match ($rule->target_type) {
            'permission' => $rule->target_value ? $this->usersWithPermission($rule->target_value) : collect(),
            'role' => $rule->target_value ? $this->uniqueUsers(User::role($rule->target_value)->get()) : collect(),
            'creator' => $this->creatorFromContext($context),
            'department_reviewers' => $this->reviewersForKlassenbuch($context['klassenbuch'] ?? null),
            default => collect(),
        };

        $users = $this->applyRuleScope($users, $rule, $context);

        if ($rule->exclude_actor) {
            $users = $this->withoutActor($users, $context['actor'] ?? null);
        }

        return $this->uniqueUsers($users);
    }

    private function applyRuleScope(Collection $users, NotificationRule $rule, array $context): Collection
    {
        if ($rule->scope !== 'current_project' || empty($context['project_id'])) {
            return $this->uniqueUsers($users);
        }

        if (method_exists($users, 'loadMissing')) {
            $users->loadMissing(['person.projekte']);
        } else {
            $users->each(fn (User $user) => $user->loadMissing(['person.projekte']));
        }

        $projektId = (int) $context['project_id'];

        return $this->uniqueUsers(
            $users->filter(fn (User $user) => $user->person?->projekte->contains('id', $projektId))
        );
    }

    private function usersWithPermission(string $permission): Collection
    {
        return $this->uniqueUsers(User::permission($permission)->get());
    }

    private function usersWithPermissionInProject(string $permission, ?int $projektId): Collection
    {
        $users = User::permission($permission)
            ->with(['person.projekte'])
            ->get();

        if (! $projektId) {
            return $this->uniqueUsers($users);
        }

        return $this->uniqueUsers(
            $users->filter(fn (User $user) => $user->person?->projekte->contains('id', $projektId))
        );
    }

    private function creatorOfMaterialanforderung(Materialanforderung $anforderung): Collection
    {
        $users = User::where('person_id', $anforderung->ersteller_id)->get();

        if ($users->isEmpty()) {
            $users = User::whereKey($anforderung->ersteller_id)->get();
        }

        return $this->uniqueUsers($users);
    }

    private function creatorFromContext(array $context): Collection
    {
        if (isset($context['anforderung'])) {
            return $this->creatorOfMaterialanforderung($context['anforderung']);
        }

        if (($context['creator_user'] ?? null) instanceof User) {
            return collect([$context['creator_user']]);
        }

        return collect();
    }

    private function projectIdForMaterialanforderung(Materialanforderung $anforderung, ?User $actor): ?int
    {
        return $actor?->current_team_id ?: $anforderung->projekt_id;
    }

    private function reviewersForKlassenbuch(?Klassenbuch $klassenbuch): Collection
    {
        if (! $klassenbuch) {
            return collect();
        }

        $projekt = $klassenbuch->gruppe?->projekt;
        $ids = collect();

        if ($projekt?->abteilung_id) {
            $ids = $ids->merge(
                DB::table('abteilungsassistents')
                    ->where('abteilung_id', $projekt->abteilung_id)
                    ->pluck('user_id')
            );

            if (Schema::hasColumn('abteilungs', 'user_id')) {
                $leitungId = DB::table('abteilungs')
                    ->where('id', $projekt->abteilung_id)
                    ->value('user_id');

                if ($leitungId) {
                    $ids->push($leitungId);
                }
            }

            if (Schema::hasColumn('abteilungs', 'personen_id')) {
                $leitungPersonId = DB::table('abteilungs')
                    ->where('id', $projekt->abteilung_id)
                    ->value('personen_id');

                if ($leitungPersonId) {
                    $userId = User::where('person_id', $leitungPersonId)->value('id');

                    if ($userId) {
                        $ids->push($userId);
                    }
                }
            }
        }

        $reviewers = User::whereIn('id', $ids->filter()->unique()->values())->get();

        if ($reviewers->isNotEmpty()) {
            return $this->uniqueUsers($reviewers);
        }

        return $this->uniqueUsers(User::role(['Abteilungsleitung', 'Assistenz der Abt.-Leitung'])->get());
    }

    private function withoutActor(Collection $users, ?User $actor): Collection
    {
        if (! $actor) {
            return $this->uniqueUsers($users);
        }

        return $this->uniqueUsers($users->reject(fn (User $user) => (int) $user->id === (int) $actor->id));
    }

    private function uniqueUsers(Collection $users): Collection
    {
        return $users
            ->filter()
            ->unique(fn (User $user) => $user->id)
            ->values();
    }
}
