<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\AuditLog; use App\Models\Facility; use App\Models\Reservation; use App\Models\ScheduleBlock; use Illuminate\Http\Request;
class ScheduleController extends Controller {
 public function index(Request $r){ $month=$r->input('month',now()->format('Y-m')); $start=\Carbon\Carbon::createFromFormat('Y-m',$month)->startOfMonth(); $end=$start->copy()->endOfMonth(); return view('admin.schedule.index',['month'=>$month,'reservations'=>Reservation::with('facility')->whereBetween('reservation_date',[$start,$end])->orderBy('reservation_date')->get(),'blocks'=>ScheduleBlock::with('facility')->whereDate('starts_on','<=',$end)->whereDate('ends_on','>=',$start)->get(),'facilities'=>Facility::orderBy('facility_name')->get()]); }
 public function store(Request $r){ $v=$r->validate(['facility_id'=>['nullable','exists:facilities,id'],'title'=>['required','string','max:255'],'reason'=>['nullable','string'],'starts_on'=>['required','date'],'ends_on'=>['required','date','after_or_equal:starts_on'],'start_time'=>['nullable','date_format:H:i'],'end_time'=>['nullable','date_format:H:i','after:start_time']]); $block=ScheduleBlock::create([...$v,'created_by'=>$r->user()->id]); AuditLog::record('schedule_block.created',$block,$v); return back()->with('success','Blackout period created.'); }
 public function destroy(ScheduleBlock $block){ AuditLog::record('schedule_block.released',$block,['title'=>$block->title]); $block->delete(); return back()->with('success','Date or slot reopened.'); }
}
