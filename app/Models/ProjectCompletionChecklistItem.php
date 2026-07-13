<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ProjectCompletionChecklistItem extends Model
{
    use HasFactory;
    protected $fillable=['project_id','label','description','required','active','sort_order'];
    protected $casts=['required'=>'boolean','active'=>'boolean'];
    public function completions(){return $this->hasMany(ParticipationCompletionChecklistCompletion::class,'checklist_item_id');}
}
