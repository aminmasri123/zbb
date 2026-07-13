<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ParticipationCompletionReport extends Model
{
    use HasFactory;
    protected $fillable=['project_person_id','version','status','completion_type','exit_date','outcome','summary','recommendations','snapshot','snapshot_sha256','created_by_user_id','approved_by_user_id','approved_at','decision_note'];
    protected $casts=['exit_date'=>'date','snapshot'=>'array','approved_at'=>'datetime'];
    public function creator(){return $this->belongsTo(User::class,'created_by_user_id');}
    public function approver(){return $this->belongsTo(User::class,'approved_by_user_id');}
    public function participation(){return $this->belongsTo(ProjektHasPersonen::class,'project_person_id');}
}
