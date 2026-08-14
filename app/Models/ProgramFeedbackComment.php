<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramFeedbackComment extends Model
{
    protected $fillable = ['program_feedback_id', 'user_id', 'body', 'is_internal'];

    protected $casts = ['is_internal' => 'boolean'];

    public function feedback()
    {
        return $this->belongsTo(ProgramFeedback::class, 'program_feedback_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
