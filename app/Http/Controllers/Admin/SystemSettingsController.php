<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\EmailTemplate; use App\Models\SystemSetting; use Illuminate\Http\Request;
class SystemSettingsController extends Controller {
 public function edit(){ $settings=SystemSetting::pluck('value','key'); $templates=EmailTemplate::get()->keyBy('key'); return view('admin.settings.edit',compact('settings','templates')); }
 public function update(Request $r){ $v=$r->validate(['maximum_booking_hours'=>['required','integer','min:1','max:24'],'minimum_notice_days'=>['required','integer','min:0','max:365'],'maximum_advance_days'=>['required','integer','min:1','max:730'],'pending_auto_cancel_hours'=>['required','integer','min:1','max:720'],'templates'=>['required','array'],'templates.*.subject'=>['required','string','max:255'],'templates.*.body'=>['required','string']]); foreach(array_diff_key($v,['templates'=>1]) as $k=>$val) SystemSetting::updateOrCreate(['key'=>$k],['value'=>(string)$val]); foreach($v['templates'] as $key=>$template) EmailTemplate::updateOrCreate(['key'=>$key],$template); return back()->with('success','System rules and email templates updated.'); }
}
