<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Raumtyp extends Model
{
    use HasFactory;

    protected $table = 'raumtypen';

    protected $fillable = ['name', 'beschreibung', 'aktiv', 'sort_order'];

    protected $casts = [
        'aktiv' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function raeume()
    {
        return $this->hasMany(Raeume::class, 'typ', 'name');
    }
}
