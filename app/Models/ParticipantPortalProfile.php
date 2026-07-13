<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipantPortalProfile extends Model
{
    use HasFactory;

    protected $fillable = ['person_id', 'professional_headline', 'career_goal', 'skills', 'interests', 'available_from', 'job_search_radius_km', 'profile_visible_to_project_staff'];
    protected $casts = ['available_from' => 'date', 'profile_visible_to_project_staff' => 'boolean'];

    public function person() { return $this->belongsTo(Personen::class); }
}
