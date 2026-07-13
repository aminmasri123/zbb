<?php

namespace App\Http\Controllers;

use App\Models\PortalCourseAssignment;
use App\Models\PortalCourseEnrollment;
use App\Models\PortalCourseMaterial;
use App\Models\PortalCourseSession;
use App\Models\PortalCourseSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PortalLearningContentController extends Controller
{
    public function sessions(Request $request)
    {
        $enrollments = PortalCourseEnrollment::query()
            ->where('status', '!=', 'cancelled')
            ->whereHas('participation', fn ($query) => $query->where('personen_id', $request->user()->person_id))
            ->whereHas('course', fn ($query) => $query->where('status', 'published'))
            ->with(['course:id,project_id,title', 'course.project:id,name,portal_feature_settings'])
            ->get()
            ->filter(fn ($enrollment) => $enrollment->course->project->portalFeatureEnabled('learning'))
            ->values();

        $sessions = PortalCourseSession::query()
            ->whereIn('course_id', $enrollments->pluck('course_id'))
            ->where('published', true)
            ->with([
                'course:id,project_id,title',
                'attendance' => fn ($query) => $query
                    ->whereIn('enrollment_id', $enrollments->pluck('id'))
                    ->select(['id', 'session_id', 'enrollment_id', 'status', 'attended_minutes']),
            ])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('ParticipantPortal/CourseSessions', ['sessions' => $sessions]);
    }

    public function downloadMaterial(Request $request, PortalCourseMaterial $material)
    {
        $this->enrollment($request, $material->course_id);
        abort_unless($material->published && (!$material->lesson_id || $material->lesson?->published), 404);
        abort_unless(Storage::disk('local')->exists($material->path), 404);
        return Storage::disk('local')->download($material->path, $material->original_name, ['Content-Type' => $material->mime_type]);
    }

    public function submit(Request $request, PortalCourseAssignment $assignment)
    {
        $enrollment = $this->enrollment($request, $assignment->course_id);
        abort_unless($assignment->published, 404);
        $data = $request->validate([
            'text_answer' => [$assignment->allow_text ? 'nullable' : 'prohibited', 'string', 'max:50000'],
            'file' => [$assignment->allow_file ? 'nullable' : 'prohibited', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt,zip'],
        ]);
        abort_if(empty(trim((string) ($data['text_answer'] ?? ''))) && !isset($data['file']), 422, 'Bitte geben Sie einen Text ein oder laden Sie eine Datei hoch.');
        $submission = PortalCourseSubmission::query()->firstOrNew(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        if (isset($data['file'])) {
            if ($submission->path) Storage::disk('local')->delete($submission->path);
            $file = $data['file'];
            $submission->fill(['original_name' => $file->getClientOriginalName(), 'path' => $file->store('participant-learning/submissions/'.$enrollment->id, 'local'), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize()]);
        }
        $submission->fill(['text_answer' => $data['text_answer'] ?? null, 'status' => 'submitted', 'submitted_at' => now(), 'score' => null, 'feedback' => null, 'reviewed_by_user_id' => null, 'reviewed_at' => null])->save();
        return response()->json(['message' => 'Abgabe wurde gespeichert.', 'submission' => $submission], 201);
    }

    public function downloadSubmission(Request $request, PortalCourseSubmission $submission)
    {
        $submission->load('assignment');
        $enrollment = $this->enrollment($request, $submission->assignment->course_id);
        abort_unless((int) $submission->enrollment_id === (int) $enrollment->id && $submission->path && Storage::disk('local')->exists($submission->path), 404);
        return Storage::disk('local')->download($submission->path, $submission->original_name, ['Content-Type' => $submission->mime_type]);
    }

    private function enrollment(Request $request, int $courseId): PortalCourseEnrollment
    {
        $enrollment = PortalCourseEnrollment::query()->where('course_id', $courseId)->where('status', '!=', 'cancelled')->whereHas('participation', fn ($query) => $query->where('personen_id', $request->user()->person_id))->with(['course', 'participation.projekt'])->firstOrFail();
        abort_unless($enrollment->course->status === 'published' && $enrollment->participation->projekt->portalFeatureEnabled('learning'), 404);
        return $enrollment;
    }
}
