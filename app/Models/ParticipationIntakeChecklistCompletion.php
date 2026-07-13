<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipationIntakeChecklistCompletion extends Model
{
    use HasFactory;

    protected $fillable = ['project_person_id', 'checklist_item_id', 'completed', 'completed_at', 'completed_by_user_id'];
    protected $casts = ['completed' => 'boolean', 'completed_at' => 'datetime'];

    public function participation() { return $this->belongsTo(ProjektHasPersonen::class, 'project_person_id'); }
    public function item() { return $this->belongsTo(ProjectIntakeChecklistItem::class, 'checklist_item_id'); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by_user_id'); }
}
