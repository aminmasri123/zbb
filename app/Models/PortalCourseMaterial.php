<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class PortalCourseMaterial extends Model{use HasFactory;protected $fillable=['course_id','lesson_id','uploaded_by_user_id','title','original_name','path','mime_type','size','published','sort_order'];protected $casts=['published'=>'boolean','size'=>'integer','sort_order'=>'integer'];public function course(){return $this->belongsTo(PortalCourse::class,'course_id');}public function lesson(){return $this->belongsTo(PortalCourseLesson::class,'lesson_id');}}
