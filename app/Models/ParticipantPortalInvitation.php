<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipantPortalInvitation extends Model
{
    use HasFactory;

    protected $fillable = ['project_person_id', 'email', 'token_hash', 'expires_at', 'accepted_at', 'invited_by_user_id'];
    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function participation() { return $this->belongsTo(ProjektHasPersonen::class, 'project_person_id'); }
    public function invitedBy() { return $this->belongsTo(User::class, 'invited_by_user_id'); }
}
