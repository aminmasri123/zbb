<?php

namespace App\Models;

use App\Models\Tage;
use App\Models\Zeiten;
use App\Models\Personen;
use App\Models\Anwesenheitsstatuten;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PersonenHasAnwesenheiten extends Model
{
    use HasFactory;

    protected $fillable = [
        'personen_id',
        'user_id',
        'tage_id',
        'zeiten_id',
        'anwesenheitsstatuten_id',
        'bemerkung',
    ];

    // Beziehungen (optional, aber nützlich)
    public function person()
    {
        return $this->belongsTo(Personen::class, 'personen_id');
    }

    public function status()
    {
        return $this->belongsTo(Anwesenheitsstatuten::class, 'anwesenheitsstatuten_id');
    }

    public function tag()
    {
        return $this->belongsTo(Tage::class, 'tage_id');
    }

    public function zeit()
    {
        return $this->belongsTo(Zeiten::class, 'zeiten_id');
    }
}
