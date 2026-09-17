<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoboCertificatePrintSetting extends Model
{
    protected $fillable = [
        'horizontal_offset_mm',
        'vertical_offset_mm',
        'row_spacing_offset_mm',
        'cross_font_size_pt',
        'updated_by',
    ];

    protected $casts = [
        'horizontal_offset_mm' => 'float',
        'vertical_offset_mm' => 'float',
        'row_spacing_offset_mm' => 'float',
        'cross_font_size_pt' => 'float',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'horizontal_offset_mm' => 0,
            'vertical_offset_mm' => 0,
            'row_spacing_offset_mm' => 0,
            'cross_font_size_pt' => 13,
        ]);
    }
}
