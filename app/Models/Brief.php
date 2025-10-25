<?php

namespace App\Models;

use App\Models\Freigabe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brief extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'title',
        'content',
    ];

    /**
     * Alle Freigaben für diesen Brief
       */
    public function freigaben()
    {
        return $this->morphMany(Freigabe::class, 'shareable_from');
    }

    /**
     * Einfacher Zugriff auf alle Empfänger (User/Projekt)
     */
    public function recipients()
    {
        return $this->freigaben->map(fn($f) => $f->shareableTo);
    }
}
