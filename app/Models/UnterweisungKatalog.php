<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnterweisungKatalog extends Model
{
    protected $table = 'unterweisung_katalog';

    public $timestamps = false;

    protected $casts = ['themen' => 'array'];

    public static function themen(): array
    {
        return static::query()->findOrFail(1)->themen;
    }
}
