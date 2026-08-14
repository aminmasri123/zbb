<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramFeedbackAttachment extends Model
{
    protected $fillable = ['program_feedback_id', 'user_id', 'original_name', 'path', 'mime_type', 'size'];

    protected $hidden = ['path'];

    protected $appends = ['download_url'];

    public function feedback()
    {
        return $this->belongsTo(ProgramFeedback::class, 'program_feedback_id');
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('program-feedback.attachments.download', $this);
    }
}
