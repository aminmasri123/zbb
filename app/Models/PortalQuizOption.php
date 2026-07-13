<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class PortalQuizOption extends Model{use HasFactory;protected $fillable=['question_id','label','is_correct','sort_order'];protected $casts=['is_correct'=>'boolean','sort_order'=>'integer'];}
