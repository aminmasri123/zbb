<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class PortalCourseLesson extends Model
{
    use HasFactory;
    protected $fillable = ['course_id','title','content','sort_order','published'];
    protected $casts = ['published'=>'boolean','sort_order'=>'integer'];
    public function course() { return $this->belongsTo(PortalCourse::class, 'course_id'); }
}
