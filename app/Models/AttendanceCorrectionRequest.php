<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class AttendanceCorrectionRequest extends Model
{
 use HasFactory;
 protected $fillable=['attendance_id','person_id','message','status','resolution_note','resolved_by_user_id','resolved_at'];
 protected $casts=['resolved_at'=>'datetime'];
 public function attendance(){return $this->belongsTo(GruppeHasPersonen::class,'attendance_id');}
 public function resolver(){return $this->belongsTo(User::class,'resolved_by_user_id');}
}
