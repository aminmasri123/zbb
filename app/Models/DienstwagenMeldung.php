<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DienstwagenMeldung extends Model
{
    use HasFactory;

    protected $table = 'dienstwagen_meldungen';

    protected $fillable = [
        'dienstwagen_id',
        'gemeldet_von_user_id',
        'gemeldet_von_personen_id',
        'verantwortlich_person_id',
        'titel',
        'kategorie',
        'prioritaet',
        'status',
        'beschreibung',
        'attachment_path',
        'erledigt_am',
    ];

    protected $casts = [
        'erledigt_am' => 'datetime',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function dienstwagen()
    {
        return $this->belongsTo(Dienstwagen::class);
    }

    public function gemeldetVon()
    {
        return $this->belongsTo(User::class, 'gemeldet_von_user_id');
    }

    public function gemeldetVonPerson()
    {
        return $this->belongsTo(Personen::class, 'gemeldet_von_personen_id');
    }

    public function verantwortlich()
    {
        return $this->belongsTo(Personen::class, 'verantwortlich_person_id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }
}
