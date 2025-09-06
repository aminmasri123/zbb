<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bereich extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'beschreibung',
    ];
<<<<<<< HEAD

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class);
    }
=======
>>>>>>> 761bc7a7e2ba9a80dd4b302a5940c8827e4459fc
}
