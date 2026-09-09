<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AptitudeAttempt extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['scores' => 'array', 'results' => 'array', 'reports' => 'array', 'tested_on' => 'date:Y-m-d', 'approved_at' => 'datetime'];

    public function profile() { return $this->belongsTo(AptitudeProfile::class); }
    public function gruppe() { return $this->belongsTo(Gruppe::class); }
    public function participation() { return $this->belongsTo(ProjektHasPersonen::class, 'project_person_id'); }
}
