<?php

namespace App\Http\Controllers;

use App\Models\ParticipantPortalInvitation;
use App\Models\ParticipantPortalProfile;
use App\Models\ParticipantPortalMessage;
use App\Models\ParticipantApplication;
use App\Models\PortalCourseEnrollment;
use App\Models\PortalCourseSession;
use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use App\Models\User;
use App\Services\Projects\ActiveProjectContext;
use App\Services\Participants\ParticipantReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ParticipantPortalController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext, private readonly ParticipantReminderService $reminderService) {}

    public function welcome()
    {
        return Inertia::render('ParticipantPortal/Welcome');
    }

    public function loginForm()
    {
        return Inertia::render('ParticipantPortal/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($credentials['email'])])
            ->whereHas('person', fn ($query) => $query->where('typ', 'teilnehmer')->where('aktiv', true))
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Die Zugangsdaten sind ungültig.'])->onlyInput('email');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('participant-portal.dashboard');
    }

    public function invite(Request $request, ProjektHasPersonen $participation)
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');
        abort_unless((int) $participation->projekt_id === (int) $project->id, 404);
        abort_unless(Personen::query()->teilnehmer()->visibleForUser($request->user())->whereKey($participation->personen_id)->exists(), 403);
        abort_if(User::query()->where('person_id', $participation->personen_id)->exists(), 422, 'Für diesen Teilnehmer besteht bereits ein Benutzerkonto.');

        $validated = $request->validate(['email' => ['required', 'email', 'max:255', 'unique:users,email']]);
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($request, $participation, $validated, $token) {
            ParticipantPortalInvitation::query()
                ->where('project_person_id', $participation->id)
                ->whereNull('accepted_at')
                ->update(['expires_at' => now()]);

            return ParticipantPortalInvitation::query()->create([
                'project_person_id' => $participation->id,
                'email' => Str::lower($validated['email']),
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
                'invited_by_user_id' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Portal-Einladung wurde erstellt.',
            'invitation_url' => route('participant-portal.invitation.show', $token),
            'expires_at' => $invitation->expires_at,
        ], 201);
    }

    public function invitation(string $token)
    {
        $invitation = $this->validInvitation($token)->load('participation.teilnehmer:id,vorname,nachname');

        return Inertia::render('ParticipantPortal/AcceptInvitation', [
            'token' => $token,
            'email' => $invitation->email,
            'participantName' => trim($invitation->participation->teilnehmer->vorname . ' ' . $invitation->participation->teilnehmer->nachname),
            'expiresAt' => $invitation->expires_at,
        ]);
    }

    public function acceptInvitation(Request $request, string $token)
    {
        $invitation = $this->validInvitation($token)->load('participation.teilnehmer');
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated) {
            abort_if(User::query()->where('person_id', $invitation->participation->personen_id)->exists(), 422, 'Das Portal wurde bereits aktiviert.');
            abort_if(User::query()->where('email', $invitation->email)->exists(), 422, 'Diese E-Mail-Adresse wird bereits verwendet.');

            $user = User::query()->create([
                'person_id' => $invitation->participation->personen_id,
                'username' => 'tn-' . $invitation->participation->personen_id . '-' . Str::lower(Str::random(5)),
                'email' => $invitation->email,
                'email_verified_at' => now(),
                'password' => Hash::make($validated['password']),
                'current_team_id' => $invitation->participation->projekt_id,
                'default_projekt_id' => $invitation->participation->projekt_id,
            ]);
            $invitation->update(['accepted_at' => now()]);
            ParticipantPortalProfile::query()->firstOrCreate(['person_id' => $invitation->participation->personen_id]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('participant-portal.dashboard');
    }

    public function dashboard(Request $request)
    {
        $person = $request->user()->person;
        $dashboard = $this->reminderService->build($request->user());

        return Inertia::render('ParticipantPortal/Dashboard', [
            'participant' => $person->only(['id', 'vorname', 'nachname', 'geburtsdatum']),
            'participations' => $dashboard['participations'],
            'profile' => ParticipantPortalProfile::query()->where('person_id', $person->id)->first(),
            'reminders' => $dashboard['reminders'],
            'unreadMessageCount' => $dashboard['unread_message_count'],
        ]);

        /* Legacy inline reminder construction retained temporarily for rollback reference.
        $participations = ProjektHasPersonen::query()
            ->where('personen_id', $person->id)
            ->with([
                'projekt:id,name,portal_feature_settings',
                'standort:id,name',
                'tasks' => fn ($query) => $query->where('visible_to_participant', true)->where('status', '!=', 'done')->orderBy('due_at'),
            ])
            ->get()
            ->each(fn ($participation) => $participation->setAttribute('portal_features', $participation->projekt->portalFeatureSettings()));
        $participationIds = $participations->pluck('id');
        $reminders = collect();

        foreach ($participations->flatMap->tasks as $task) {
            if ($task->due_at && $task->due_at->lte(now()->addDays(14))) {
                $reminders->push(['type' => 'task', 'title' => $task->title, 'detail' => 'Aufgabe ist bis ' . $task->due_at->format('d.m.Y') . ' fällig.', 'at' => $task->due_at->toISOString(), 'href' => route('participant-portal.dashboard')]);
            }
        }

        ParticipantApplication::query()->whereIn('project_person_id', $participationIds)
            ->whereNotNull('next_action_at')->whereBetween('next_action_at', [now()->startOfDay(), now()->addDays(14)->endOfDay()])
            ->whereNotIn('status', ['accepted', 'rejected', 'withdrawn'])->get()
            ->each(fn ($application) => $reminders->push(['type' => 'application', 'title' => $application->title, 'detail' => 'Nächster Bewerbungsschritt am ' . $application->next_action_at->format('d.m.Y') . '.', 'at' => $application->next_action_at->toISOString(), 'href' => route('participant-portal.jobs.index')]));

        PortalCourseEnrollment::query()->whereIn('project_person_id', $participationIds)->whereIn('status', ['enrolled', 'in_progress'])
            ->whereHas('course', fn ($query) => $query->whereBetween('starts_at', [now(), now()->addDays(14)]))->with('course:id,title,starts_at')->get()
            ->each(fn ($enrollment) => $reminders->push(['type' => 'course', 'title' => $enrollment->course->title, 'detail' => 'Kursbeginn am ' . $enrollment->course->starts_at->format('d.m.Y H:i') . ' Uhr.', 'at' => $enrollment->course->starts_at->toISOString(), 'href' => route('participant-portal.learning.index')]));

        $activeEnrollments = PortalCourseEnrollment::query()
            ->whereIn('project_person_id', $participationIds)
            ->whereIn('status', ['enrolled', 'in_progress'])
            ->with('course.project:id,portal_feature_settings')
            ->get()
            ->filter(fn ($enrollment) => $enrollment->course->project->portalFeatureEnabled('learning'));

        PortalCourseSession::query()
            ->whereIn('course_id', $activeEnrollments->pluck('course_id'))
            ->where('published', true)
            ->whereBetween('starts_at', [now(), now()->addDays(14)])
            ->orderBy('starts_at')
            ->get()
            ->each(fn ($session) => $reminders->push([
                'type' => 'course_session',
                'title' => $session->title,
                'detail' => 'Kurstermin am '.$session->starts_at->format('d.m.Y H:i').' Uhr'.($session->location ? ' · '.$session->location : '').'.',
                'at' => $session->starts_at->toISOString(),
                'href' => route('participant-portal.learning.sessions.index'),
            ]));

        $unreadMessageCount = ParticipantPortalMessage::query()->whereIn('project_person_id', $participationIds)
            ->where('sender_kind', 'staff')->whereNull('participant_read_at')->count();
        if ($unreadMessageCount > 0) {
            $reminders->push(['type' => 'message', 'title' => $unreadMessageCount === 1 ? 'Eine ungelesene Nachricht' : $unreadMessageCount . ' ungelesene Nachrichten', 'detail' => 'Ihr Projektteam hat Ihnen geschrieben.', 'at' => now()->toISOString(), 'href' => route('participant-portal.messages.index')]);
        }

        return Inertia::render('ParticipantPortal/Dashboard', [
            'participant' => $person->only(['id', 'vorname', 'nachname', 'geburtsdatum']),
            'participations' => $participations,
            'profile' => ParticipantPortalProfile::query()->where('person_id', $person->id)->first(),
            'reminders' => $reminders->sortBy('at')->values(),
            'unreadMessageCount' => $unreadMessageCount,
        ]); */
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'professional_headline' => ['nullable', 'string', 'max:160'],
            'career_goal' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'string', 'max:3000'],
            'interests' => ['nullable', 'string', 'max:3000'],
            'available_from' => ['nullable', 'date'],
            'job_search_radius_km' => ['nullable', 'integer', 'min:0', 'max:500'],
            'profile_visible_to_project_staff' => ['required', 'boolean'],
        ]);

        $profile = ParticipantPortalProfile::query()->updateOrCreate(
            ['person_id' => $request->user()->person_id],
            $validated
        );

        return response()->json(['message' => 'Profil wurde gespeichert.', 'profile' => $profile]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('participant-portal.welcome');
    }

    private function validInvitation(string $token): ParticipantPortalInvitation
    {
        return ParticipantPortalInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }
}
