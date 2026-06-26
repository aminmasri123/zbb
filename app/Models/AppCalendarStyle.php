<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppCalendarStyle extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'label',
        'background_color',
        'text_color',
    ];
}
