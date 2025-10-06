<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Baenke extends Model
{
    use HasFactory;


     protected $fillable = ['name', 'iban', 'blz', 'model_type', 'model_id'];

    // Automatisch verschlüsseln/dekodieren
    public function setIbanAttribute($value)
    {
        $this->attributes['iban'] = Crypt::encryptString($value);
    }

    public function getIbanAttribute($value)
    {
        return Crypt::decryptString($value);
    }
}
