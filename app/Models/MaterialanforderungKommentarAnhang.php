<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialanforderungKommentarAnhang extends Model
{
    protected $table = 'materialanforderung_kommentar_anhaenge';

    protected $fillable = ['kommentar_id', 'original_name', 'path', 'mime_type', 'size'];

    public function kommentar()
    {
        return $this->belongsTo(MaterialanforderungKommentar::class, 'kommentar_id');
    }
}
