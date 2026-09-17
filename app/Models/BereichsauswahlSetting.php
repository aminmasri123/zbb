<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BereichsauswahlSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_id',
        'partner_id',
        'schuljahr',
        'teil',
        'auswahl_anzahl',
        'bereich_ids',
        'public_token',
        'zugang_aktiv',
        'user_create',
        'user_update',
    ];

    protected $casts = [
        'zugang_aktiv' => 'boolean',
        'auswahl_anzahl' => 'integer',
        'bereich_ids' => 'array',
    ];

    /** Match the same school-year spellings as the participant list. */
    public function scopeForContext($query, int $projektId, int $partnerId, string $schuljahr, string $teil)
    {
        $value = trim($schuljahr);
        preg_match('/\d{4}/', $value, $matches);
        $startYear = $matches[0] ?? trim(explode('/', $value, 2)[0]);

        return $query->where('projekt_id', $projektId)
            ->where('partner_id', $partnerId)
            ->where('teil', $teil)
            ->where(function ($yearQuery) use ($value, $startYear) {
                $yearQuery->where('schuljahr', $value);
                if ($startYear !== '') {
                    $yearQuery->orWhere('schuljahr', $startYear)
                        ->orWhere('schuljahr', 'like', $startYear . '/%')
                        ->orWhere('schuljahr', 'like', $startYear . '-%');
                }
            });
    }

    public function scopePreferConfigured($query)
    {
        // A default created by the former exact-year lookup must not override a saved choice.
        return $query->orderByRaw('CASE WHEN user_update IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id', 'id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'id');
    }
}
