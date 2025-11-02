<?php

namespace App\Models;

use App\Models\Leistungsbezuege;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PersonenHasSozialedaten extends Model
{
    use HasFactory;
    protected $fillable = [
        'wohnsitz_stabil',
        'leistungsbezug_id',
        'behinderung',
        'migrationshintergrund',
        'gefluechtet',
        'drittstaatsangehoerig',
        'person_id'
    ];



    public function leistungsbezug(): hasOne
    {
        return $this->hasOne(Leistungsbezuege::class);
    }
}
