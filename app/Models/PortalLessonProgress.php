<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class PortalLessonProgress extends Model
{
    use HasFactory;
    protected $table = 'portal_lesson_progress';
    protected $fillable = ['enrollment_id','lesson_id','completed','completed_at'];
    protected $casts = ['completed'=>'boolean','completed_at'=>'datetime'];
    public function lesson() { return $this->belongsTo(PortalCourseLesson::class, 'lesson_id'); }
}
