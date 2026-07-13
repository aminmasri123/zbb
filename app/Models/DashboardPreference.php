<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DashboardPreference extends Model
{
    protected $fillable = ['user_id', 'hidden_cards'];
    protected $casts = ['hidden_cards' => 'array'];
    public function user() { return $this->belongsTo(User::class); }
}
