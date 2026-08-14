<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramFeedback extends Model
{
    use HasFactory;

    public const TYPES = ['suggestion', 'bug'];
    public const PRIORITIES = ['low', 'normal', 'high', 'critical'];
    public const STATUSES = ['new', 'review', 'planned', 'in_progress', 'testing', 'released', 'rejected', 'duplicate'];

    protected $table = 'program_feedback';

    protected $fillable = [
        'reference',
        'user_id',
        'assigned_to_user_id',
        'type',
        'title',
        'description',
        'expected_result',
        'area',
        'priority',
        'status',
        'page_url',
        'browser',
        'app_version',
        'release_version',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function comments()
    {
        return $this->hasMany(ProgramFeedbackComment::class)->oldest();
    }

    public function attachments()
    {
        return $this->hasMany(ProgramFeedbackAttachment::class)->oldest();
    }

    public function history()
    {
        return $this->hasMany(ProgramFeedbackHistory::class)->latest();
    }
}
