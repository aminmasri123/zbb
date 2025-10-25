<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Freigabe extends Model
{
    use HasFactory;
    protected $fillable = [
        'shareable_from_id',
        'shareable_from_type',
        'shareable_to_id',
        'shareable_to_type',
        'shared_by',
        'right',
    ];



    /**
     * Das Modell, das freigegeben wird (z. B. Letter oder Aktennotiz)
     */
    public function shareableFrom()
    {
        return $this->morphTo();
    }

    /**
     * Das Modell, an das freigegeben wurde (z. B. User oder Project)
     */
    public function shareableTo()
    {
        return $this->morphTo();
    }

    /**
     * Der Benutzer, der die Freigabe erstellt hat
     */
    public function sharedByUser()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
