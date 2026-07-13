<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class EducationMeasureStatusHistory extends Model
{
    use HasFactory;
    protected $table='education_measure_status_history';
    protected $fillable=['education_measure_id','from_status','to_status','note','changed_by_user_id'];
    public function changer(){return $this->belongsTo(User::class,'changed_by_user_id');}
}
