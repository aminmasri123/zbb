<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ParticipationCompletionChecklistCompletion extends Model
{
    use HasFactory;
    protected $fillable=['project_person_id','checklist_item_id','completed','note','completed_by_user_id','completed_at'];
    protected $casts=['completed'=>'boolean','completed_at'=>'datetime'];
    public function completedBy(){return $this->belongsTo(User::class,'completed_by_user_id');}
    public function item(){return $this->belongsTo(ProjectCompletionChecklistItem::class,'checklist_item_id');}
}
