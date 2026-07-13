<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class PortalCourse extends Model
{
    use HasFactory;
    protected $fillable = ['project_id','created_by_user_id','title','description','status','starts_at','ends_at','capacity','self_enrollment'];
    protected $casts = ['starts_at'=>'date','ends_at'=>'date','self_enrollment'=>'boolean','capacity'=>'integer'];
    public function project() { return $this->belongsTo(Projekt::class, 'project_id'); }
    public function lessons() { return $this->hasMany(PortalCourseLesson::class, 'course_id')->orderBy('sort_order')->orderBy('id'); }
    public function enrollments() { return $this->hasMany(PortalCourseEnrollment::class, 'course_id'); }
    public function materials() { return $this->hasMany(PortalCourseMaterial::class, 'course_id')->orderBy('sort_order')->orderBy('id'); }
    public function assignments() { return $this->hasMany(PortalCourseAssignment::class, 'course_id')->orderBy('sort_order')->orderBy('id'); }
    public function quizzes() { return $this->hasMany(PortalCourseQuiz::class, 'course_id')->orderBy('sort_order')->orderBy('id'); }
    public function sessions() { return $this->hasMany(PortalCourseSession::class, 'course_id')->orderBy('starts_at'); }
}
