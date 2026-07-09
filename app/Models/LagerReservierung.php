<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LagerReservierung extends Model
{
    use HasFactory;

    public const STATUS_RESERVIERT = 'reserviert';
    public const STATUS_AUSGEGEBEN = 'ausgegeben';
    public const STATUS_STORNIERT = 'storniert';

    protected $table = 'lager_reservierungen';

    protected $fillable = [
        'lager_artikel_id',
        'angefordert_von_user_id',
        'angefordert_von_personen_id',
        'menge',
        'status',
        'zweck',
        'bemerkung',
        'ausgegeben_at',
        'storniert_at',
    ];

    protected $casts = [
        'menge' => 'decimal:2',
        'ausgegeben_at' => 'datetime',
        'storniert_at' => 'datetime',
    ];

    public function artikel()
    {
        return $this->belongsTo(LagerArtikel::class, 'lager_artikel_id');
    }

    public function angefordertVonUser()
    {
        return $this->belongsTo(User::class, 'angefordert_von_user_id');
    }

    public function angefordertVonPerson()
    {
        return $this->belongsTo(Personen::class, 'angefordert_von_personen_id');
    }

    public function bewegungen()
    {
        return $this->hasMany(LagerBewegung::class, 'lager_reservierung_id');
    }
}
