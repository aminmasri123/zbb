<?php

namespace App\Models;

use App\Models\User;
use App\Models\Abteilung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Projekt extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'kostenstelle',
        'abteilung_id',
    ];
    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class);
    }


    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
