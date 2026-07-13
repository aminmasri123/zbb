<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipantPortalMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_person_id',
        'sender_user_id',
        'sender_kind',
        'body',
        'participant_read_at',
        'staff_read_at',
    ];

    protected $casts = [
        'participant_read_at' => 'datetime',
        'staff_read_at' => 'datetime',
    ];

    public function participation()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'project_person_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
