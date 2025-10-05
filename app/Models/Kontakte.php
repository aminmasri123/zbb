<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kontakte extends Model
{
    use HasFactory;
    protected $fillable = ['model_type', 'model_id', 'kontakttyp_id', 'wert', 'bemerkung'];


    public function kontakttyp()
    {
        return $this->belongsTo(Kontakttypen::class, 'kontakttyp_id');
    }
}
