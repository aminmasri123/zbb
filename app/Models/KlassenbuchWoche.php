<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KlassenbuchWoche extends Model
{
    use HasFactory;

    protected $table = 'klassenbuch_wochen';

    protected $fillable = [
        'klassenbuch_id',
        'jahr',
        'kalenderwoche',
        'start_datum',
        'end_datum',
        'status',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'locked_by_user_id',
        'submitted_at',
        'reviewed_at',
        'locked_at',
    ];

    protected $casts = [
        'start_datum' => 'date',
        'end_datum' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function klassenbuch()
    {
        return $this->belongsTo(Klassenbuch::class, 'klassenbuch_id');
    }

    public function eintraege()
    {
        return $this->hasMany(KlassenbuchEintrag::class, 'klassenbuch_woche_id')
            ->orderBy('datum')
            ->orderBy('stunde')
            ->orderBy('id');
    }

    public function kommentare()
    {
        return $this->hasMany(KlassenbuchKommentar::class, 'klassenbuch_woche_id')
            ->latest();
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isLocked(): bool
    {
        return $this->status === 'gesperrt';
    }
}
