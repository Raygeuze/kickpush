<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'name']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'name']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('project_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'name']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'status']);
        });

        Schema::table('timer_sessions', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'stopped_at']);
        });

        Schema::table('financial_years', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'start_year']);
        });

        Schema::table('business_expenses', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'incurred_on']);
        });

        $this->backfillTeamIds();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'incurred_on']);
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('financial_years', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'start_year']);
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('timer_sessions', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'stopped_at']);
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'status']);
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'name']);
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'name']);
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'name']);
            $table->dropConstrainedForeignId('team_id');
        });
    }

    private function backfillTeamIds(): void
    {
        $ownedTeamMap = DB::table('teams')
            ->selectRaw('MIN(id) as id, user_id')
            ->groupBy('user_id')
            ->pluck('id', 'user_id');

        $users = DB::table('users')
            ->select(['id', 'current_team_id'])
            ->get();

        $teamIdByUserId = [];

        foreach ($users as $user) {
            $teamId = $user->current_team_id ?: ($ownedTeamMap[$user->id] ?? null);

            if ($teamId !== null) {
                $teamIdByUserId[(int) $user->id] = (int) $teamId;
            }
        }

        foreach ($teamIdByUserId as $userId => $teamId) {
            DB::table('clients')
                ->where('user_id', $userId)
                ->whereNull('team_id')
                ->update(['team_id' => $teamId]);

            DB::table('projects')
                ->where('user_id', $userId)
                ->whereNull('team_id')
                ->update(['team_id' => $teamId]);

            DB::table('invoices')
                ->where('user_id', $userId)
                ->whereNull('team_id')
                ->update(['team_id' => $teamId]);

            DB::table('timer_sessions')
                ->where('user_id', $userId)
                ->whereNull('team_id')
                ->update(['team_id' => $teamId]);

            DB::table('financial_years')
                ->where('user_id', $userId)
                ->whereNull('team_id')
                ->update(['team_id' => $teamId]);

            DB::table('business_expenses')
                ->where('user_id', $userId)
                ->whereNull('team_id')
                ->update(['team_id' => $teamId]);
        }

        $projectTeamById = DB::table('projects')
            ->whereNotNull('team_id')
            ->pluck('team_id', 'id');

        $taskRows = DB::table('tasks')
            ->select(['id', 'project_id'])
            ->whereNull('team_id')
            ->get();

        foreach ($taskRows as $taskRow) {
            $teamId = $projectTeamById[$taskRow->project_id] ?? null;

            if ($teamId !== null) {
                DB::table('tasks')
                    ->where('id', $taskRow->id)
                    ->update(['team_id' => (int) $teamId]);
            }
        }

        $clientTeamById = DB::table('clients')
            ->whereNotNull('team_id')
            ->pluck('team_id', 'id');

        $invoiceRows = DB::table('invoices')
            ->select(['id', 'client_id'])
            ->whereNull('team_id')
            ->whereNotNull('client_id')
            ->get();

        foreach ($invoiceRows as $invoiceRow) {
            $teamId = $clientTeamById[$invoiceRow->client_id] ?? null;

            if ($teamId !== null) {
                DB::table('invoices')
                    ->where('id', $invoiceRow->id)
                    ->update(['team_id' => (int) $teamId]);
            }
        }

        $invoiceTeamById = DB::table('invoices')
            ->whereNotNull('team_id')
            ->pluck('team_id', 'id');

        $taskTeamById = DB::table('tasks')
            ->whereNotNull('team_id')
            ->pluck('team_id', 'id');

        $sessionRows = DB::table('timer_sessions')
            ->select(['id', 'invoice_id', 'task_id'])
            ->whereNull('team_id')
            ->get();

        foreach ($sessionRows as $sessionRow) {
            $teamId = null;

            if ($sessionRow->invoice_id !== null) {
                $teamId = $invoiceTeamById[$sessionRow->invoice_id] ?? null;
            }

            if ($teamId === null && $sessionRow->task_id !== null) {
                $teamId = $taskTeamById[$sessionRow->task_id] ?? null;
            }

            if ($teamId !== null) {
                DB::table('timer_sessions')
                    ->where('id', $sessionRow->id)
                    ->update(['team_id' => (int) $teamId]);
            }
        }
    }
};
