<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserHasProjekt extends Model
{
    use HasFactory;
    protected $fillable = [
        'projekt_id',
        'user_id',
    ];

}
