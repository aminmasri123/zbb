<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'assignee_person_id',
        'project_id',
        'project_person_id',
        'team_id',
        'workflow_template_id',
        'title',
        'description',
        'status',
        'priority',
        'sort_order',
        'due_at',
        'started_at',
        'completed_at',
        'visibility',
        'visible_to_participant',
    ];

    protected $casts = [
        'due_at' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'visible_to_participant' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(Personen::class, 'assignee_person_id');
    }

    public function shares()
    {
        return $this->morphMany(AppShare::class, 'shareable');
    }

    public function workflowTemplate()
    {
        return $this->belongsTo(AppTaskWorkflowTemplate::class, 'workflow_template_id');
    }

    public function participation()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'project_person_id');
    }
}
