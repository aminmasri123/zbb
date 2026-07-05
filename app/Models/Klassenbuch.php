<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Klassenbuch extends Model
{
    use HasFactory;

    protected $table = 'klassenbuecher';

    protected $fillable = [
        'gruppe_id',
        'klassenbuch_typ_id',
        'created_by_user_id',
        'locked_by_user_id',
        'titel',
        'schuljahr',
        'lehrjahr',
        'status',
        'locked_at',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function gruppe()
    {
        return $this->belongsTo(Gruppe::class);
    }

    public function typ()
    {
        return $this->belongsTo(KlassenbuchTyp::class, 'klassenbuch_typ_id');
    }

    public function wochen()
    {
        return $this->hasMany(KlassenbuchWoche::class, 'klassenbuch_id')
            ->orderBy('jahr')
            ->orderBy('kalenderwoche');
    }

    public function offeneWochen()
    {
        return $this->hasMany(KlassenbuchWoche::class, 'klassenbuch_id')
            ->whereIn('status', ['offen', 'korrektur']);
    }

    public function pruefungWochen()
    {
        return $this->hasMany(KlassenbuchWoche::class, 'klassenbuch_id')
            ->where('status', 'eingereicht');
    }

    public function korrekturWochen()
    {
        return $this->hasMany(KlassenbuchWoche::class, 'klassenbuch_id')
            ->where('status', 'korrektur');
    }

    public function gesperrteWochen()
    {
        return $this->hasMany(KlassenbuchWoche::class, 'klassenbuch_id')
            ->where('status', 'gesperrt');
    }
}
