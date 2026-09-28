<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScheduleBlock extends Model {
    protected $fillable=['facility_id','starts_on','ends_on','start_time','end_time','title','reason','created_by'];
    protected $casts=['starts_on'=>'date','ends_on'=>'date'];
    public function facility(){ return $this->belongsTo(Facility::class); }
    public function creator(){ return $this->belongsTo(User::class,'created_by'); }
}
