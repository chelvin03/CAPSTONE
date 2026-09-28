<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SystemSetting extends Model {
    protected $fillable=['key','value'];
    public static function getValue(string $key, mixed $default=null): mixed { return static::where('key',$key)->value('value') ?? $default; }
}
