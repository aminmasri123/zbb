<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ParticipantCareerDocument extends Model {use HasFactory;protected $fillable=['person_id','created_by_user_id','type','title','template_key','content','is_default'];protected $casts=['content'=>'array','is_default'=>'boolean'];public function applications(){return $this->belongsToMany(ParticipantApplication::class,'participant_application_career_documents','career_document_id','application_id')->withPivot('sort_order');}}
