<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffConversation extends Model
{
    protected $fillable = [
        'type', 'name', 'project_id', 'created_by_user_id', 'retention_days', 'last_message_at',
    ];

    protected $casts = ['last_message_at' => 'datetime'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'staff_conversation_members', 'conversation_id', 'user_id')
            ->withPivot(['joined_at', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(StaffMessage::class, 'conversation_id');
    }

    public function project()
    {
        return $this->belongsTo(Projekt::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
