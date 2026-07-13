<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class PortalQuizQuestion extends Model{use HasFactory;protected $fillable=['quiz_id','question','type','points','sort_order'];protected $casts=['points'=>'decimal:2','sort_order'=>'integer'];public function quiz(){return $this->belongsTo(PortalCourseQuiz::class,'quiz_id');}public function options(){return $this->hasMany(PortalQuizOption::class,'question_id')->orderBy('sort_order')->orderBy('id');}}
