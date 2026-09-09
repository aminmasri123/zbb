<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipationCareerGoal extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['documented_on' => 'date:Y-m-d'];
}
