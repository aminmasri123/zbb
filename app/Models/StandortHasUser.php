<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StandortHasUser extends Model
{
    use HasFactory;

    protected $table = 'standort_has_users';

    protected $fillable = [
        'standort_id',
        'user_id',
    ];
}
