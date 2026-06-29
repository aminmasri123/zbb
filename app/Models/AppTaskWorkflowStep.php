<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppTaskWorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'title',
        'description',
        'assignee_person_id',
        'status',
        'priority',
        'due_offset_days',
        'sort_order',
    ];

    public function template()
    {
        return $this->belongsTo(AppTaskWorkflowTemplate::class, 'template_id');
    }

    public function assignee()
    {
        return $this->belongsTo(Personen::class, 'assignee_person_id');
    }
}
