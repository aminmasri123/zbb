<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BopRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_id', 'partner_id', 'schuljahr', 'teil', 'school_type', 'parts', 'planned_classes',
        'first_visit_date', 'last_visit_date', 'status',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'first_visit_date' => 'date',
        'last_visit_date' => 'date',
        'planned_classes' => 'array',
        'parts' => 'array',
    ];

    public function scopeForSchuljahr($query, $schuljahr)
    {
        $value = trim((string) $schuljahr);
        preg_match('/\d{4}/', $value, $matches);
        $startYear = $matches[0] ?? trim(explode('/', $value, 2)[0]);

        return $query->where(function ($yearQuery) use ($value, $startYear) {
            $yearQuery->where('schuljahr', $value);

            if ($startYear !== '') {
                $yearQuery
                    ->orWhere('schuljahr', $startYear)
                    ->orWhere('schuljahr', 'like', $startYear . '/%')
                    ->orWhere('schuljahr', 'like', $startYear . '-%');
            }
        });
    }

    public function phases()
    {
        return $this->hasMany(BopPhaseSchedule::class)->orderBy('id');
    }

    public function project()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
