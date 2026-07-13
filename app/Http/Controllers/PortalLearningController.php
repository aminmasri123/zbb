<?php

namespace App\Http\Controllers;

use App\Models\PortalCourse;
use App\Models\PortalCourseEnrollment;
use App\Models\PortalCourseLesson;
use App\Models\PortalLessonProgress;
use App\Models\ProjektHasPersonen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PortalLearningController extends Controller
{
    public function index(Request $request)
    {
        $participations = ProjektHasPersonen::query()->where('personen_id',$request->user()->person_id)->with('projekt')->get()->filter(fn($item)=>$item->projekt->portalFeatureEnabled('learning'))->values();
        $ids = $participations->pluck('id');
        $projectIds = $participations->pluck('projekt_id');
        $courses = PortalCourse::query()->whereIn('project_id',$projectIds)->where('status','published')
            ->with(['project:id,name','lessons'=>fn($q)=>$q->where('published',true),'materials'=>fn($q)=>$q->where('published',true),'assignments'=>fn($q)=>$q->where('published',true)->with(['submissions'=>fn($s)=>$s->whereHas('enrollment',fn($e)=>$e->whereIn('project_person_id',$ids))]),'quizzes'=>fn($q)=>$q->where('published',true)->with(['questions.options'=>fn($o)=>$o->select(['id','question_id','label','sort_order']),'attempts'=>fn($a)=>$a->whereHas('enrollment',fn($e)=>$e->whereIn('project_person_id',$ids))]),'sessions'=>fn($q)=>$q->where('published',true)->with(['attendance'=>fn($a)=>$a->whereHas('enrollment',fn($e)=>$e->whereIn('project_person_id',$ids))]),'enrollments'=>fn($q)=>$q->whereIn('project_person_id',$ids)->with(['progress','submissions','sessionAttendance'])])
            ->orderBy('starts_at')->get();
        return Inertia::render('ParticipantPortal/Learning',['participations'=>$participations,'courses'=>$courses]);
    }

    public function enroll(Request $request, PortalCourse $course)
    {
        $data=$request->validate(['project_person_id'=>['required','integer']]);
        $participation=$this->participation($request,$data['project_person_id'],$course);
        abort_unless($course->status==='published' && $course->self_enrollment,404);
        $enrollment=DB::transaction(function() use($course,$participation){
            $locked=PortalCourse::query()->lockForUpdate()->findOrFail($course->id);
            $existing=PortalCourseEnrollment::query()->where('course_id',$locked->id)->where('project_person_id',$participation->id)->first();
            if($existing && $existing->status!=='cancelled') return $existing;
            $count=$locked->enrollments()->where('status','!=','cancelled')->count();
            abort_if($locked->capacity!==null && $count >= $locked->capacity,422,'Der Kurs ist ausgebucht.');
            return PortalCourseEnrollment::query()->updateOrCreate(['course_id'=>$locked->id,'project_person_id'=>$participation->id],['status'=>'enrolled','enrolled_at'=>now(),'completed_at'=>null]);
        });
        return response()->json(['message'=>'Sie sind eingeschrieben.','enrollment'=>$enrollment],201);
    }

    public function updateProgress(Request $request, PortalCourseEnrollment $enrollment, PortalCourseLesson $lesson)
    {
        $enrollment->load('course');
        $this->participation($request,$enrollment->project_person_id,$enrollment->course);
        abort_unless((int)$lesson->course_id===(int)$enrollment->course_id && $lesson->published,404);
        $completed=(bool)$request->validate(['completed'=>['required','boolean']])['completed'];
        $progress=PortalLessonProgress::query()->updateOrCreate(['enrollment_id'=>$enrollment->id,'lesson_id'=>$lesson->id],['completed'=>$completed,'completed_at'=>$completed?now():null]);
        $publishedCount=$enrollment->course->lessons()->where('published',true)->count();
        $completedCount=$enrollment->progress()->where('completed',true)->whereHas('lesson',fn($q)=>$q->where('published',true))->count();
        $enrollment->update(['status'=>$publishedCount>0&&$completedCount===$publishedCount?'completed':($completedCount>0?'in_progress':'enrolled'),'completed_at'=>$publishedCount>0&&$completedCount===$publishedCount?now():null]);
        return response()->json(['message'=>'Lernfortschritt gespeichert.','progress'=>$progress,'enrollment'=>$enrollment->fresh()]);
    }

    private function participation(Request $request,int $id,PortalCourse $course): ProjektHasPersonen
    {
        $participation=ProjektHasPersonen::query()->whereKey($id)->where('personen_id',$request->user()->person_id)->with('projekt')->firstOrFail();
        abort_unless((int)$participation->projekt_id===(int)$course->project_id && $participation->projekt->portalFeatureEnabled('learning'),404);
        return $participation;
    }
}
