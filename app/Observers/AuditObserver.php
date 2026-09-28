<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLog::recordEvent($this->action($model, 'created'), $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if ($changes === []) {
            return;
        }
        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }
        AuditLog::recordEvent($this->action($model, 'updated'), $model, $old, $changes);
    }

    public function deleted(Model $model): void
    {
        AuditLog::recordEvent($this->action($model, 'deleted'), $model, $model->getOriginal(), null);
    }

    private function action(Model $model, string $event): string
    {
        return Str::snake(class_basename($model)).'.'.$event;
    }
}
