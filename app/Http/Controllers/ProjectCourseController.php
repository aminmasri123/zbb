<?php

namespace App\Http\Controllers;

use App\Models\Personen;
use App\Models\PortalCourse;
use App\Models\PortalCourseEnrollment;
use App\Models\PortalCourseLesson;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProjectCourseController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function index(Request $request, Projekt $projekt)
    {
        $this->authorizeProject($request, $projekt);
        return Inertia::render('Projekt/Courses', [
            'projekt' => $projekt->only(['id','name']),
            'courses' => PortalCourse::query()->where('project_id', $projekt->id)
                ->with(['lessons', 'materials', 'assignments.submissions.enrollment.participation.teilnehmer:id,vorname,nachname', 'quizzes.questions.options', 'quizzes.attempts.enrollment.participation.teilnehmer:id,vorname,nachname', 'sessions.attendance.enrollment.participation.teilnehmer:id,vorname,nachname', 'enrollments.participation.teilnehmer:id,vorname,nachname'])
                ->orderByDesc('created_at')->get(),
            'participants' => ProjektHasPersonen::query()->where('projekt_id', $projekt->id)
                ->whereHas('teilnehmer')->with('teilnehmer:id,vorname,nachname')->get(['id','projekt_id','personen_id','status']),
        ]);
    }

    public function store(Request $request, Projekt $projekt)
    {
        $this->authorizeProject($request, $projekt);
        $data = $request->validate($this->courseRules());
        $course = PortalCourse::query()->create([...$data, 'project_id'=>$projekt->id, 'created_by_user_id'=>$request->user()->id]);
        return response()->json(['message'=>'Kurs wurde angelegt.','course'=>$course->load(['lessons','enrollments'])], 201);
    }

    public function update(Request $request, PortalCourse $course)
    {
        $this->authorizeProject($request, $course->project);
        $course->update($request->validate($this->courseRules()));
        return response()->json(['message'=>'Kurs wurde aktualisiert.','course'=>$course->fresh()->load(['lessons','materials','assignments.submissions.enrollment.participation.teilnehmer:id,vorname,nachname','enrollments.participation.teilnehmer:id,vorname,nachname'])]);
    }

    public function storeLesson(Request $request, PortalCourse $course)
    {
        $this->authorizeProject($request, $course->project);
        $lesson = $course->lessons()->create($request->validate($this->lessonRules()));
        return response()->json(['message'=>'Lektion wurde angelegt.','lesson'=>$lesson], 201);
    }

    public function updateLesson(Request $request, PortalCourseLesson $lesson)
    {
        $lesson->load('course.project');
        $this->authorizeProject($request, $lesson->course->project);
        $lesson->update($request->validate($this->lessonRules()));
        return response()->json(['message'=>'Lektion wurde aktualisiert.','lesson'=>$lesson->fresh()]);
    }

    public function enroll(Request $request, PortalCourse $course)
    {
        $this->authorizeProject($request, $course->project);
        $data = $request->validate(['project_person_ids'=>['required','array','min:1'],'project_person_ids.*'=>['integer']]);
        $participations = ProjektHasPersonen::query()->where('projekt_id',$course->project_id)->whereIn('id',$data['project_person_ids'])->whereHas('teilnehmer')->get();
        abort_unless($participations->count() === count(array_unique($data['project_person_ids'])), 422, 'Alle Teilnahmen müssen zum Kursprojekt gehören.');

        DB::transaction(function () use ($course, $participations) {
            $locked = PortalCourse::query()->lockForUpdate()->findOrFail($course->id);
            $existing = $locked->enrollments()->where('status','!=','cancelled')->count();
            $newIds = $participations->pluck('id')->reject(fn ($id) => $locked->enrollments()->where('project_person_id',$id)->where('status','!=','cancelled')->exists());
            abort_if($locked->capacity !== null && $existing + $newIds->count() > $locked->capacity, 422, 'Die Kurskapazität würde überschritten.');
            foreach ($participations as $participation) {
                PortalCourseEnrollment::query()->updateOrCreate(
                    ['course_id'=>$locked->id,'project_person_id'=>$participation->id],
                    ['status'=>'enrolled','enrolled_at'=>now(),'completed_at'=>null]
                );
            }
        });

        return response()->json(['message'=>'Teilnehmer wurden eingeschrieben.','enrollments'=>$course->enrollments()->with('participation.teilnehmer:id,vorname,nachname')->get()]);
    }

    private function authorizeProject(Request $request, Projekt $project): void
    {
        $active = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($active && (int)$active->id === (int)$project->id, 404);
        abort_unless($project->portalFeatureEnabled('learning'), 404);
    }

    private function courseRules(): array
    {
        return ['title'=>['required','string','max:255'],'description'=>['nullable','string','max:5000'],'status'=>['required',Rule::in(['draft','published','archived'])],'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date','after_or_equal:starts_at'],'capacity'=>['nullable','integer','min:1','max:10000'],'self_enrollment'=>['required','boolean']];
    }

    private function lessonRules(): array
    {
        return ['title'=>['required','string','max:255'],'content'=>['nullable','string','max:50000'],'sort_order'=>['required','integer','min:0','max:9999'],'published'=>['required','boolean']];
    }
}
