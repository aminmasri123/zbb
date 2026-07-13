<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class PortalCourseSession extends Model{use HasFactory;protected $fillable=['course_id','title','description','starts_at','ends_at','mode','location','online_url','published'];protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime','published'=>'boolean'];public function course(){return $this->belongsTo(PortalCourse::class,'course_id');}public function attendance(){return $this->hasMany(PortalCourseSessionAttendance::class,'session_id');}}
