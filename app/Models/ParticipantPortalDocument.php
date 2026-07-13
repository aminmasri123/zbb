<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ParticipantPortalDocument extends Model
{
 use HasFactory;
 protected $fillable=['project_person_id','uploaded_by_user_id','original_name','path','mime_type','size','category','status','visible_to_participant','review_note','reviewed_by_user_id','reviewed_at'];
 protected $casts=['visible_to_participant'=>'boolean','reviewed_at'=>'datetime','size'=>'integer'];
 public function participation(){return $this->belongsTo(ProjektHasPersonen::class,'project_person_id');}
 public function uploader(){return $this->belongsTo(User::class,'uploaded_by_user_id');}
 public function reviewer(){return $this->belongsTo(User::class,'reviewed_by_user_id');}
}
