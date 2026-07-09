<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DienstwagenVerlauf extends Model
{
    use HasFactory;

    protected $table = 'dienstwagen_verlaeufe';

    public $timestamps = false;

    protected $fillable = [
        'dienstwagen_id',
        'user_id',
        'person_id',
        'aktion',
        'titel',
        'beschreibung',
        'related_type',
        'related_id',
        'changes_json',
        'created_at',
    ];

    protected $casts = [
        'changes_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function dienstwagen()
    {
        return $this->belongsTo(Dienstwagen::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function person()
    {
        return $this->belongsTo(Personen::class);
    }
}
