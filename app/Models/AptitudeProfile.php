<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AptitudeProfile extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['definition' => 'array'];
}
