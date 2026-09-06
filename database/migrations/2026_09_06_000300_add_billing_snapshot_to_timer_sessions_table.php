<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timer_sessions', function (Blueprint $table) {
            $table->decimal('hourly_rate_snapshot', 10, 2)->nullable()->after('duration_seconds');
            $table->string('hourly_rate_source', 16)->nullable()->after('hourly_rate_snapshot');
            $table->string('currency_snapshot', 3)->nullable()->after('hourly_rate_source');
            $table->timestamp('rate_snapshot_at')->nullable()->after('currency_snapshot');
        });

        $capturedAt = now();

        DB::table('timer_sessions')
            ->orderBy('id')
            ->chunkById(500, function ($sessions) use ($capturedAt): void {
                $userRates = DB::table('users')
                    ->whereIn('id', $sessions->pluck('user_id')->filter()->unique())
                    ->pluck('hourly_rate', 'id');

                $invoiceClients = DB::table('invoices')
                    ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
                    ->whereIn('invoices.id', $sessions->pluck('invoice_id')->filter()->unique())
                    ->get(['invoices.id', 'clients.hourly_rate', 'clients.currency'])
                    ->keyBy('id');

                $taskClients = DB::table('tasks')
                    ->join('projects', 'projects.id', '=', 'tasks.project_id')
                    ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
                    ->whereIn('tasks.id', $sessions->pluck('task_id')->filter()->unique())
                    ->get(['tasks.id', 'clients.hourly_rate', 'clients.currency'])
                    ->keyBy('id');

                foreach ($sessions as $session) {
                    $client = $session->invoice_id
                        ? $invoiceClients->get($session->invoice_id)
                        : $taskClients->get($session->task_id);
                    $userRate = (float) ($userRates[$session->user_id] ?? 0);
                    $clientRate = (float) optional($client)->hourly_rate;
                    $usesUserRate = $userRate > 0;

                    DB::table('timer_sessions')
                        ->where('id', $session->id)
                        ->update([
                            'hourly_rate_snapshot' => $usesUserRate ? $userRate : $clientRate,
                            'hourly_rate_source' => $usesUserRate ? 'user' : 'client',
                            'currency_snapshot' => $client && $client->currency
                                ? strtoupper((string) $client->currency)
                                : null,
                            'rate_snapshot_at' => $capturedAt,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('timer_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'hourly_rate_snapshot',
                'hourly_rate_source',
                'currency_snapshot',
                'rate_snapshot_at',
            ]);
        });
    }
};