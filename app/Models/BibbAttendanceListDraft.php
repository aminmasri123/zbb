<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BibbAttendanceListDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'draft_hash',
        'projekt_id',
        'partner_id',
        'schuljahr',
        'teil',
        'payload',
        'revision',
        'user_create',
        'user_update',
    ];

    protected $casts = [
        'payload' => 'array',
        'revision' => 'integer',
    ];
}
