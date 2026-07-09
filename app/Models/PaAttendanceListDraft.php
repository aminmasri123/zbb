<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaAttendanceListDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'draft_hash',
        'projekt_id',
        'partner_id',
        'schuljahr',
        'teil',
        'export_mode',
        'klasse',
        'payload',
        'final_pdf_path',
        'finalized_at',
        'expires_at',
        'revision',
        'user_create',
        'user_update',
    ];

    protected $casts = [
        'payload' => 'array',
        'finalized_at' => 'datetime',
        'expires_at' => 'datetime',
        'revision' => 'integer',
    ];
}
