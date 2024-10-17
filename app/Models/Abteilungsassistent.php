<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Abteilungsassistent extends Model
{
    use HasFactory;
    protected $fillable = [
        'abteilung_id',
        'user_id',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Die Beziehung zur Abteilung
    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class);
    }



}
