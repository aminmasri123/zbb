<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectIntakeChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'label', 'description', 'required', 'active', 'sort_order'];
    protected $casts = ['required' => 'boolean', 'active' => 'boolean'];

    public function project() { return $this->belongsTo(Projekt::class, 'project_id'); }
    public function completions() { return $this->hasMany(ParticipationIntakeChecklistCompletion::class, 'checklist_item_id'); }
}
