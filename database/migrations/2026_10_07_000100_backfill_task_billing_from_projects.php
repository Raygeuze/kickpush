<?php

use App\Models\WorkEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make task billing explicit before removing project-level inheritance.
     */
    public function up(): void
    {
        DB::table('tasks')
            ->select(['id', 'project_id', 'billing_mode', 'unit_label', 'unit_label_plural', 'unit_rate'])
            ->orderBy('id')
            ->chunkById(500, function ($tasks): void {
                $projectIds = collect($tasks)
                    ->pluck('project_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $projectsById = DB::table('projects')
                    ->whereIn('id', $projectIds)
                    ->get(['id', 'billing_mode', 'unit_label', 'unit_label_plural', 'unit_rate'])
                    ->keyBy('id');

                foreach ($tasks as $task) {
                    $project = $projectsById->get($task->project_id);

                    $mode = $task->billing_mode ?? optional($project)->billing_mode;
                    $mode = in_array($mode, WorkEntry::MODES, true) ? $mode : WorkEntry::MODE_TIME;

                    $label = $task->unit_label ?? optional($project)->unit_label;
                    $plural = $task->unit_label_plural ?? optional($project)->unit_label_plural;
                    $rate = $task->unit_rate ?? optional($project)->unit_rate;

                    if ($plural === null && $label !== null) {
                        $plural = $label.'s';
                    }

                    DB::table('tasks')
                        ->where('id', $task->id)
                        ->update([
                            'billing_mode' => $mode,
                            'unit_label' => $label,
                            'unit_label_plural' => $plural,
                            'unit_rate' => $rate,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // No-op: this data migration intentionally keeps explicit task billing values.
    }
};
