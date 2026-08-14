<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_user_id', 'body', 'materialanforderung_id', 'expires_at',
    ];

    protected $casts = ['expires_at' => 'datetime'];

    public function conversation()
    {
        return $this->belongsTo(StaffConversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function attachments()
    {
        return $this->hasMany(StaffMessageAttachment::class, 'message_id');
    }

    public function materialanforderung()
    {
        return $this->belongsTo(Materialanforderung::class, 'materialanforderung_id');
    }
}
