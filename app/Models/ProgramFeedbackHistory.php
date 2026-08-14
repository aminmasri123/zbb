<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramFeedbackHistory extends Model
{
    protected $table = 'program_feedback_history';

    protected $fillable = ['program_feedback_id', 'user_id', 'from_status', 'to_status', 'note'];

    public function feedback()
    {
        return $this->belongsTo(ProgramFeedback::class, 'program_feedback_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
