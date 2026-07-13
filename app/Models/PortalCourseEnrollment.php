<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class PortalCourseEnrollment extends Model
{
    use HasFactory;
    protected $fillable = ['course_id','project_person_id','status','enrolled_at','completed_at'];
    protected $casts = ['enrolled_at'=>'datetime','completed_at'=>'datetime'];
    public function course() { return $this->belongsTo(PortalCourse::class, 'course_id'); }
    public function participation() { return $this->belongsTo(ProjektHasPersonen::class, 'project_person_id'); }
    public function progress() { return $this->hasMany(PortalLessonProgress::class, 'enrollment_id'); }
    public function submissions() { return $this->hasMany(PortalCourseSubmission::class, 'enrollment_id'); }
    public function quizAttempts() { return $this->hasMany(PortalQuizAttempt::class, 'enrollment_id'); }
    public function sessionAttendance() { return $this->hasMany(PortalCourseSessionAttendance::class, 'enrollment_id'); }
}
