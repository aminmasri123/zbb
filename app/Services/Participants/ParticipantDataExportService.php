<?php

namespace App\Services\Participants;

use App\Models\AppTask;
use App\Models\GruppeHasPersonen;
use App\Models\ParticipantApplication;
use App\Models\ParticipantConsentEvent;
use App\Models\ParticipantContactChangeRequest;
use App\Models\ParticipantCvEntry;
use App\Models\ParticipantCvVersion;
use App\Models\ParticipantPortalDocument;
use App\Models\ParticipantPortalMessage;
use App\Models\ParticipationCompletionReport;
use App\Models\Personen;
use App\Models\PortalCourseEnrollment;
use App\Models\ProjektHasPersonen;
use App\Models\User;

class ParticipantDataExportService
{
    public function build(Personen $person): array
    {
        $person->load(['adresses', 'kontaktes', 'baenke', 'sozialedaten', 'portalProfile', 'abschluesse', 'praktika.statusHistory.changer:id,name', 'notizen', 'fahrtabrechnungen', 'zielgruppen']);
        $participations = ProjektHasPersonen::query()->where('personen_id', $person->id)->with(['projekt:id,name', 'standort:id,name'])->get();
        $participationIds = $participations->pluck('id');
        $portalUser = User::query()->where('person_id', $person->id)->first(['id', 'email', 'email_verified_at', 'created_at']);

        return [
            'export' => ['generated_at' => now()->toISOString(), 'format_version' => 2, 'subject_person_id' => $person->id],
            'person' => $person->makeHidden(['created_at', 'updated_at'])->toArray(),
            'portal_account' => $portalUser?->toArray(),
            'contact_change_history' => $portalUser ? ParticipantContactChangeRequest::query()->where('user_id', $portalUser->id)->get()->toArray() : [],
            'resume' => [
                'entries' => ParticipantCvEntry::query()->where('person_id', $person->id)->orderBy('type')->orderBy('sort_order')->get()->toArray(),
                'versions' => ParticipantCvVersion::query()->where('person_id', $person->id)->orderBy('version')->get()->toArray(),
            ],
            'participations' => $participations->toArray(),
            'participation_completion_reports' => ParticipationCompletionReport::query()
                ->whereIn('project_person_id', $participationIds)
                ->with(['creator:id,name', 'approver:id,name'])
                ->orderBy('project_person_id')->orderBy('version')->get()->toArray(),
            'portal' => [
                'tasks' => AppTask::query()->whereIn('project_person_id', $participationIds)->where('visible_to_participant', true)->get()->toArray(),
                'applications' => ParticipantApplication::query()->whereIn('project_person_id', $participationIds)->with(['statusHistory', 'documents'])->get()->toArray(),
                'documents' => ParticipantPortalDocument::query()->whereIn('project_person_id', $participationIds)->get(['id', 'project_person_id', 'original_name', 'mime_type', 'size', 'category', 'status', 'visible_to_participant', 'created_at'])->toArray(),
                'messages' => ParticipantPortalMessage::query()->whereIn('project_person_id', $participationIds)->get()->toArray(),
                'consent_events' => ParticipantConsentEvent::query()->whereIn('project_person_id', $participationIds)->get()->toArray(),
                'course_enrollments' => PortalCourseEnrollment::query()->whereIn('project_person_id', $participationIds)->with(['course:id,title,starts_at,ends_at', 'progress', 'submissions.assignment:id,title,due_at,max_points', 'quizAttempts', 'sessionAttendance.session:id,title,starts_at,ends_at,mode,location,online_url'])->get()->toArray(),
            ],
            'attendance' => GruppeHasPersonen::query()->where('personen_id', $person->id)->with(['gruppe.projekt:id,name', 'tag:id,datum', 'status:id,status,abkuerzung', 'zeitgeplant:id,startzeit,endzeit', 'zeittatsaechlich:id,startzeit,endzeit'])->get()->toArray(),
        ];
    }
}
