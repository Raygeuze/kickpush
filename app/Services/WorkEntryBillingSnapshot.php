<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;

class WorkEntryBillingSnapshot
{
    public function attributes(?User $user, ?Client $client, ?Task $task = null): array
    {
        if ($task) {
            $task->loadMissing('project');
        }

        $project = optional($task)->project;
        $userRate = (float) optional($user)->hourly_rate;
        $clientRate = (float) optional($client)->hourly_rate;
        $usesUserRate = $userRate > 0;
        $currency = $client && $client->currency
            ? strtoupper(trim((string) $client->currency))
            : null;

        return [
            'hourly_rate_snapshot' => $usesUserRate ? $userRate : $clientRate,
            'hourly_rate_source' => $usesUserRate ? 'user' : 'client',
            'currency_snapshot' => $currency,
            'rate_snapshot_at' => now(),
            'user_id_snapshot' => optional($user)->id,
            'user_name_snapshot' => optional($user)->name,
            'task_id_snapshot' => optional($task)->id,
            'task_name_snapshot' => optional($task)->name,
            'project_id_snapshot' => optional($project)->id,
            'project_name_snapshot' => optional($project)->name,
            'client_id_snapshot' => optional($client)->id,
            'client_name_snapshot' => optional($client)->name,
        ];
    }

    /**
     * Unit pricing is resolved from the task, never from the user/client hourly waterfall.
     */
    public function unitAttributes(?Task $task): array
    {
        if (!$task) {
            return [
                'unit_rate_snapshot' => null,
                'unit_label_snapshot' => null,
                'unit_label_plural_snapshot' => null,
            ];
        }

        return [
            'unit_rate_snapshot' => $task->resolvedUnitRate(),
            'unit_label_snapshot' => $task->resolvedUnitLabel(),
            'unit_label_plural_snapshot' => $task->resolvedUnitLabelPlural(),
        ];
    }

    public function applyIfMissing(WorkEntry $session, ?Client $client = null): void
    {
        if ($session->rate_snapshot_at !== null) {
            return;
        }

        $this->apply($session, $client);
    }

    public function apply(WorkEntry $session, ?Client $client = null): void
    {

        $session->loadMissing(['user:id,hourly_rate', 'task.project.client']);
        $resolvedClient = $client ?? optional(optional($session->task)->project)->client;
        $attributes = $this->attributes($session->user, $resolvedClient, $session->task);

        $identitySnapshotFields = [
            'user_id_snapshot',
            'user_name_snapshot',
            'task_id_snapshot',
            'task_name_snapshot',
            'project_id_snapshot',
            'project_name_snapshot',
            'client_id_snapshot',
            'client_name_snapshot',
        ];

        foreach ($identitySnapshotFields as $field) {
            if ($attributes[$field] === null && $session->{$field} !== null) {
                $attributes[$field] = $session->{$field};
            }
        }

        $missingRateSource = ($session->hourly_rate_source === 'user' && !$session->user)
            || ($session->hourly_rate_source === 'client' && !$resolvedClient);

        if ($session->rate_snapshot_at !== null && $missingRateSource) {
            $attributes['hourly_rate_snapshot'] = $session->hourly_rate_snapshot;
            $attributes['hourly_rate_source'] = $session->hourly_rate_source;
            $attributes['currency_snapshot'] = $session->currency_snapshot;
            $attributes['rate_snapshot_at'] = $session->rate_snapshot_at;
        } elseif ($attributes['currency_snapshot'] === null && $session->currency_snapshot !== null) {
            $attributes['currency_snapshot'] = $session->currency_snapshot;
        }

        if ($session->isUnitBased()) {
            foreach ($this->unitAttributes($session->task) as $field => $value) {
                $attributes[$field] = $value ?? $session->{$field};
            }
        }

        $session->forceFill($attributes);
    }
}
