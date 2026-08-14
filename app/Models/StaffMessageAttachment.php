<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMessageAttachment extends Model
{
    protected $fillable = ['message_id', 'original_name', 'path', 'mime_type', 'size'];

    public function message()
    {
        return $this->belongsTo(StaffMessage::class, 'message_id');
    }
}
