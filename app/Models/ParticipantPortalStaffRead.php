<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipantPortalStaffRead extends Model
{
    protected $fillable = [
        'project_person_id',
        'user_id',
        'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];
}
