<?php

namespace App\Services\Participants;

use App\Models\ParticipantApplication;
use App\Models\ParticipantNotificationPreference;
use App\Models\ParticipantPortalMessage;
use App\Models\PortalCourseEnrollment;
use App\Models\PortalCourseSession;
use App\Models\ProjektHasPersonen;
use App\Models\User;

class ParticipantReminderService
{
    public const CATEGORIES = ['task','application','course','course_session','message'];

    public function build(User $user, string $channel = 'in_app'): array
    {
        $preferences = $this->preferences($user)->keyBy('category');
        $enabled = fn (string $category) => (bool) ($preferences->get($category)?->{$channel.'_enabled'} ?? ($channel === 'in_app'));
        $days = fn (string $category) => (int) ($preferences->get($category)?->days_before ?? 14);
        $participations = ProjektHasPersonen::query()->where('personen_id', $user->person_id)->with(['projekt:id,name,portal_feature_settings','standort:id,name','tasks' => fn ($query) => $query->where('visible_to_participant',true)->where('status','!=','done')->orderBy('due_at')])->get()->each(fn($p)=>$p->setAttribute('portal_features',$p->projekt->portalFeatureSettings()));
        $ids=$participations->pluck('id');$reminders=collect();

        if($enabled('task'))foreach($participations->flatMap->tasks as $task)if($task->due_at&&$task->due_at->lte(now()->addDays($days('task'))))$reminders->push(['type'=>'task','title'=>$task->title,'detail'=>'Aufgabe ist bis '.$task->due_at->format('d.m.Y').' fällig.','at'=>$task->due_at->toISOString(),'href'=>route('participant-portal.dashboard')]);
        if($enabled('application'))ParticipantApplication::query()->whereIn('project_person_id',$ids)->whereNotNull('next_action_at')->whereBetween('next_action_at',[now()->startOfDay(),now()->addDays($days('application'))->endOfDay()])->whereNotIn('status',['accepted','rejected','withdrawn'])->get()->each(fn($a)=>$reminders->push(['type'=>'application','title'=>$a->title,'detail'=>'Nächster Bewerbungsschritt am '.$a->next_action_at->format('d.m.Y').'.','at'=>$a->next_action_at->toISOString(),'href'=>route('participant-portal.jobs.index')]));
        if($enabled('course'))PortalCourseEnrollment::query()->whereIn('project_person_id',$ids)->whereIn('status',['enrolled','in_progress'])->whereHas('course',fn($q)=>$q->whereBetween('starts_at',[now(),now()->addDays($days('course'))]))->with('course:id,title,starts_at')->get()->each(fn($e)=>$reminders->push(['type'=>'course','title'=>$e->course->title,'detail'=>'Kursbeginn am '.$e->course->starts_at->format('d.m.Y H:i').' Uhr.','at'=>$e->course->starts_at->toISOString(),'href'=>route('participant-portal.learning.index')]));
        $activeEnrollments=PortalCourseEnrollment::query()->whereIn('project_person_id',$ids)->whereIn('status',['enrolled','in_progress'])->with('course.project:id,portal_feature_settings')->get()->filter(fn($e)=>$e->course->project->portalFeatureEnabled('learning'));
        if($enabled('course_session'))PortalCourseSession::query()->whereIn('course_id',$activeEnrollments->pluck('course_id'))->where('published',true)->whereBetween('starts_at',[now(),now()->addDays($days('course_session'))])->orderBy('starts_at')->get()->each(fn($s)=>$reminders->push(['type'=>'course_session','title'=>$s->title,'detail'=>'Kurstermin am '.$s->starts_at->format('d.m.Y H:i').' Uhr'.($s->location?' · '.$s->location:'').'.','at'=>$s->starts_at->toISOString(),'href'=>route('participant-portal.learning.sessions.index')]));
        $unread=ParticipantPortalMessage::query()->whereIn('project_person_id',$ids)->where('sender_kind','staff')->whereNull('participant_read_at')->count();
        if($enabled('message')&&$unread>0)$reminders->push(['type'=>'message','title'=>$unread===1?'Eine ungelesene Nachricht':$unread.' ungelesene Nachrichten','detail'=>'Ihr Projektteam hat Ihnen geschrieben.','at'=>now()->toISOString(),'href'=>route('participant-portal.messages.index')]);
        return ['participations'=>$participations,'reminders'=>$reminders->sortBy('at')->values(),'unread_message_count'=>$unread,'preferences'=>$preferences->values()];
    }

    public function preferences(User $user)
    {
        $stored=ParticipantNotificationPreference::query()->where('user_id',$user->id)->get()->keyBy('category');
        return collect(self::CATEGORIES)->map(fn($category)=>$stored->get($category)??new ParticipantNotificationPreference(['user_id'=>$user->id,'category'=>$category,'in_app_enabled'=>true,'email_enabled'=>false,'days_before'=>14]));
    }
}
