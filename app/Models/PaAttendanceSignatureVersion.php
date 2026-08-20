<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaAttendanceSignatureVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'subject_hash',
        'version',
        'draft_id',
        'projekt_id',
        'partner_id',
        'person_id',
        'schuljahr',
        'teil',
        'list_type',
        'signature_key',
        'day_key',
        'signed_for_date',
        'day_type',
        'day_label',
        'class_name',
        'action',
        'signature_ciphertext',
        'signature_sha256',
        'restored_from_version_id',
        'source_draft_revision',
        'actor_user_id',
        'actor_name_snapshot',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'signed_for_date' => 'date',
        'source_draft_revision' => 'integer',
        'created_at' => 'datetime',
    ];

    public function participant()
    {
        return $this->belongsTo(Personen::class, 'person_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
