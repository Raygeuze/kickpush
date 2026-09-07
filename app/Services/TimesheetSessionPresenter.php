<?php

namespace App\Services;

use App\Models\TimerSession;
use Illuminate\Support\Facades\Gate;

class TimesheetSessionPresenter
{
    public const RELATIONS = [
        'user:id,name',
        'invoice:id,status',
        'task:id,name,project_id',
        'task.project:id,name,client_id',
        'task.project.client:id,name',
    ];

    public function present(TimerSession $session, string $timezone, $generatedAt = null): array
    {
        $generatedAt = $generatedAt ?: now();
        $localStart = $session->started_at->copy()->setTimezone($timezone);
        $invoiceStatus = optional($session->invoice)->status;

        return [
            'id' => (int) $session->id,
            'user_id' => $session->user_id ?? $session->user_id_snapshot,
            'user_name' => optional($session->user)->name ?? $session->user_name_snapshot ?? 'Unknown user',
            'task_id' => $session->task_id ?? $session->task_id_snapshot,
            'task_name' => optional($session->task)->name ?? $session->task_name_snapshot ?? 'General',
            'project_id' => optional(optional($session->task)->project)->id ?? $session->project_id_snapshot,
            'project_name' => optional(optional($session->task)->project)->name ?? $session->project_name_snapshot ?? 'Unassigned Project',
            'client_id' => optional(optional(optional($session->task)->project)->client)->id ?? $session->client_id_snapshot,
            'client_name' => optional(optional(optional($session->task)->project)->client)->name ?? $session->client_name_snapshot ?? 'Unassigned Client',
            'invoice_id' => $session->invoice_id,
            'invoice_status' => $invoiceStatus,
            'invoice_locked' => in_array($invoiceStatus, ['finalized', 'paid'], true),
            'started_at' => $session->started_at->toIso8601String(),
            'started_time' => $localStart->format('H:i'),
            'stopped_time' => $session->stopped_at
                ? $session->stopped_at->copy()->setTimezone($timezone)->format('H:i')
                : null,
            'day_key' => $localStart->toDateString(),
            'elapsed_seconds' => $session->elapsedSeconds($generatedAt),
            'is_running' => $session->isRunning(),
            'is_paused' => $session->isPaused(),
            'can_update' => Gate::allows('update', $session),
            'can_delete' => Gate::allows('delete', $session),
            'can_operate' => Gate::allows('operate', $session),
        ];
    }
}
