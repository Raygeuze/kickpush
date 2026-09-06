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
            $table->unsignedBigInteger('user_id_snapshot')->nullable()->after('user_id');
            $table->string('user_name_snapshot')->nullable()->after('user_id_snapshot');
            $table->unsignedBigInteger('task_id_snapshot')->nullable()->after('task_id');
            $table->string('task_name_snapshot')->nullable()->after('task_id_snapshot');
            $table->unsignedBigInteger('project_id_snapshot')->nullable()->after('task_name_snapshot');
            $table->string('project_name_snapshot')->nullable()->after('project_id_snapshot');
            $table->unsignedBigInteger('client_id_snapshot')->nullable()->after('project_name_snapshot');
            $table->string('client_name_snapshot')->nullable()->after('client_id_snapshot');
            $table->index(['team_id', 'project_id_snapshot', 'started_at'], 'timer_sessions_project_snapshot_started_index');
        });

        DB::table('timer_sessions')
            ->orderBy('id')
            ->chunkById(500, function ($sessions): void {
                $users = DB::table('users')
                    ->whereIn('id', $sessions->pluck('user_id')->filter()->unique())
                    ->get(['id', 'name'])
                    ->keyBy('id');

                $tasks = DB::table('tasks')
                    ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
                    ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
                    ->whereIn('tasks.id', $sessions->pluck('task_id')->filter()->unique())
                    ->get([
                        'tasks.id',
                        'tasks.name',
                        'projects.id as project_id',
                        'projects.name as project_name',
                        'clients.id as client_id',
                        'clients.name as client_name',
                    ])
                    ->keyBy('id');

                foreach ($sessions as $session) {
                    $user = $users->get($session->user_id);
                    $task = $tasks->get($session->task_id);

                    DB::table('timer_sessions')
                        ->where('id', $session->id)
                        ->update([
                            'user_id_snapshot' => $session->user_id,
                            'user_name_snapshot' => optional($user)->name,
                            'task_id_snapshot' => $session->task_id,
                            'task_name_snapshot' => optional($task)->name,
                            'project_id_snapshot' => optional($task)->project_id,
                            'project_name_snapshot' => optional($task)->project_name,
                            'client_id_snapshot' => optional($task)->client_id,
                            'client_name_snapshot' => optional($task)->client_name,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('timer_sessions', function (Blueprint $table) {
            $table->dropIndex('timer_sessions_project_snapshot_started_index');
            $table->dropColumn([
                'user_id_snapshot',
                'user_name_snapshot',
                'task_id_snapshot',
                'task_name_snapshot',
                'project_id_snapshot',
                'project_name_snapshot',
                'client_id_snapshot',
                'client_name_snapshot',
            ]);
        });
    }
};