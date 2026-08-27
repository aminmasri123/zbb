<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternshipEmailTemplate extends Model
{
    use HasFactory;

    public const LABELS = [
        'initial' => 'Erste E-Mail',
        'reminder_1' => '1. Erinnerung',
        'reminder_2' => '2. Erinnerung',
    ];

    protected $fillable = [
        'key',
        'subject',
        'body',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'attachment_size',
        'updated_by_user_id',
    ];

    protected $casts = [
        'attachment_size' => 'integer',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
