<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LagerBewegung extends Model
{
    use HasFactory;

    public const TYP_EINGANG = 'eingang';
    public const TYP_AUSGANG = 'ausgang';
    public const TYP_KORREKTUR = 'korrektur';

    protected $table = 'lager_bewegungen';

    protected $fillable = [
        'lager_artikel_id',
        'lager_reservierung_id',
        'gebucht_von_user_id',
        'gebucht_von_personen_id',
        'typ',
        'menge',
        'bestand_nachher',
        'bemerkung',
    ];

    protected $casts = [
        'menge' => 'decimal:2',
        'bestand_nachher' => 'decimal:2',
    ];

    public function artikel()
    {
        return $this->belongsTo(LagerArtikel::class, 'lager_artikel_id');
    }

    public function reservierung()
    {
        return $this->belongsTo(LagerReservierung::class, 'lager_reservierung_id');
    }

    public function gebuchtVonUser()
    {
        return $this->belongsTo(User::class, 'gebucht_von_user_id');
    }

    public function gebuchtVonPerson()
    {
        return $this->belongsTo(Personen::class, 'gebucht_von_personen_id');
    }
}
