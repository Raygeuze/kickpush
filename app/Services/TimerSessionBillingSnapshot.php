<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TimerSession;
use App\Models\User;

class TimerSessionBillingSnapshot
{
    public function attributes(?User $user, ?Client $client): array
    {
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
        ];
    }

    public function applyIfMissing(TimerSession $session, ?Client $client = null): void
    {
        if ($session->rate_snapshot_at !== null) {
            return;
        }

        $this->apply($session, $client);
    }

    public function apply(TimerSession $session, ?Client $client = null): void
    {

        $session->loadMissing(['user:id,hourly_rate', 'task.project.client']);
        $resolvedClient = $client ?? optional(optional($session->task)->project)->client;

        $session->forceFill($this->attributes($session->user, $resolvedClient));
    }
}