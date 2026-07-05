<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KlassenbuchKommentar extends Model
{
    use HasFactory;

    protected $table = 'klassenbuch_kommentare';

    protected $fillable = [
        'klassenbuch_woche_id',
        'user_id',
        'typ',
        'intern',
        'text',
        'edited_at',
    ];

    protected $casts = [
        'intern' => 'boolean',
        'edited_at' => 'datetime',
    ];

    public function woche()
    {
        return $this->belongsTo(KlassenbuchWoche::class, 'klassenbuch_woche_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
