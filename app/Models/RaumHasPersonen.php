<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaumHasPersonen extends Model
{
    use HasFactory;

    public const TYPE_BUERO = 'buero';
    public const TYPE_ARBEITSBEREICH = 'arbeitsbereich';

    protected $table = 'raum_has_personens';

    public $fillable = [
        'bemerkung',
        'raum_id',
        'projekt_has_personen_id',
        'assignment_type',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public static function assignmentTypes(): array
    {
        return [
            self::TYPE_BUERO,
            self::TYPE_ARBEITSBEREICH,
        ];
    }

    public function raum()
    {
        return $this->belongsTo(Raeume::class, 'raum_id');
    }

    public function projektHasPersonen()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'projekt_has_personen_id');
    }
}
