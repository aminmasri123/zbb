<?php

namespace App\Models;

use App\Models\User;
use App\Models\Personen;
use App\Models\Abteilung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Abteilungsassistent extends Model
{
    use HasFactory;
    protected $table = 'abteilungsassistents';
    public $timestamps = false; // Deaktiviert Zeitstempel, wenn sie nicht benötigt werden
    protected $fillable = ['abteilung_id', 'user_id'];

    public function personen()
    {
        return $this->belongsToMany(Personen::class, 'abteilungsassistents', 'abteilung_id', 'user_id');

    }

    // Die Beziehung zur Abteilung
    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class);
    }



}
