<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    private function currentTeamIdOrFail(): int
    {
        $user = Auth::user();

        abort_unless($user && $user->currentTeam, 403, 'Select a team to continue.');

        return (int) $user->currentTeam->id;
    }

    public function list(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $teamId = $this->currentTeamIdOrFail();

        $validated = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'project_id' => 'nullable|integer|exists:projects,id',
        ]);

        $query = Task::query()
            ->where('team_id', $teamId)
            ->with(['client:id,name', 'project:id,name,client_id'])
            ->orderBy('name');

        if (isset($validated['client_id'])) {
            $query->where('client_id', (int) $validated['client_id']);
        }

        if (isset($validated['project_id'])) {
            $query->where('project_id', (int) $validated['project_id']);
        }

        return response()->json([
            'tasks' => $query->get(),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'project_id' => 'required|integer|exists:projects,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $client = $this->findClientForActorOrFail((int) $validated['client_id']);
        $project = $this->findProjectForActorOrFail((int) $validated['project_id']);
        $taskName = trim((string) $validated['name']);

        if ($taskName === '') {
            return response()->json([
                'message' => 'Task name is required.',
            ], 422);
        }

        if ((int) $project->client_id !== (int) $client->id) {
            return response()->json([
                'message' => 'Selected project does not belong to this client.',
            ], 422);
        }

        if ($project->is_active === false) {
            return response()->json([
                'message' => 'Cannot create a task under an archived project.',
            ], 422);
        }

        $nameExists = Task::query()
            ->where('project_id', $project->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($taskName)])
            ->exists();

        if ($nameExists) {
            return response()->json([
                'message' => 'A task with this name already exists in the selected project.',
            ], 422);
        }

        $task = Task::create([
            'team_id' => $this->currentTeamIdOrFail(),
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => $taskName,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'is_default' => false,
        ]);

        return response()->json([
            'message' => 'Task created.',
            'task' => $task->load(['client:id,name', 'project:id,name,client_id']),
        ], 201);
    }

    public function update(Request $request, int $taskId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $task = $this->findTaskForActorOrFail($taskId);

        $validated = $request->validate([
            'project_id' => 'sometimes|required|integer|exists:projects,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ]);

        if (array_key_exists('project_id', $validated)) {
            $project = $this->findProjectForActorOrFail((int) $validated['project_id']);

            if ((int) $project->client_id !== (int) $task->client_id) {
                return response()->json([
                    'message' => 'Selected project does not belong to this task\'s client.',
                ], 422);
            }

            if ($project->is_active === false) {
                return response()->json([
                    'message' => 'Cannot move a task into an archived project.',
                ], 422);
            }

            $task->project_id = $project->id;
        }

        if (array_key_exists('name', $validated)) {
            $taskName = trim((string) $validated['name']);

            if ($taskName === '') {
                return response()->json([
                    'message' => 'Task name is required.',
                ], 422);
            }

            $projectId = (int) ($task->project_id);
            $nameExists = Task::query()
                ->where('project_id', $projectId)
                ->where('id', '!=', $task->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($taskName)])
                ->exists();

            if ($nameExists) {
                return response()->json([
                    'message' => 'A task with this name already exists in this project.',
                ], 422);
            }

            $task->name = $taskName;
        }

        if (array_key_exists('description', $validated)) {
            $task->description = $validated['description'];
        }

        if (array_key_exists('is_active', $validated)) {
            if ((bool) $validated['is_active'] === false && $task->is_default) {
                return response()->json([
                    'message' => 'Set another task as default before archiving this default task.',
                ], 422);
            }

            $task->is_active = (bool) $validated['is_active'];
        }

        if (array_key_exists('is_default', $validated) && (bool) $validated['is_default'] && $task->is_active === false) {
            return response()->json([
                'message' => 'Only active tasks can be set as default.',
            ], 422);
        }

        DB::transaction(function () use ($task, $validated): void {
            if (array_key_exists('is_default', $validated)) {
                $isDefault = (bool) $validated['is_default'];

                if ($isDefault) {
                    Task::query()
                        ->where('client_id', $task->client_id)
                        ->where('id', '!=', $task->id)
                        ->update(['is_default' => false]);
                }

                $task->is_default = $isDefault;
            }

            $task->save();
        });

        return response()->json([
            'message' => 'Task updated.',
            'task' => $task->fresh()->load(['client:id,name', 'project:id,name,client_id']),
        ]);
    }

    public function destroy(int $taskId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $task = $this->findTaskForActorOrFail($taskId);

        $hasSessions = $task->timerSessions()->exists();

        if ($hasSessions) {
            return response()->json([
                'message' => 'This task is already used by timer sessions and cannot be deleted.',
            ], 422);
        }

        if ($task->is_default) {
            $replacementTask = Task::query()
                ->where('client_id', $task->client_id)
                ->where('id', '!=', $task->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if (!$replacementTask) {
                return response()->json([
                    'message' => 'Set another active task before deleting the default task.',
                ], 422);
            }

            DB::transaction(function () use ($task, $replacementTask): void {
                $replacementTask->is_default = true;
                $replacementTask->save();
                $task->delete();
            });

            return response()->json([
                'message' => 'Task deleted. Default task moved to another active task.',
            ]);
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted.',
        ]);
    }

    private function findTaskForActorOrFail(int $taskId): Task
    {
        $teamId = $this->currentTeamIdOrFail();

        $task = Task::query()
            ->whereKey($taskId)
            ->where('team_id', $teamId)
            ->first();

        abort_unless($task !== null, 404, 'Task not found.');

        return $task;
    }

    private function findProjectForActorOrFail(int $projectId): Project
    {
        $teamId = $this->currentTeamIdOrFail();

        $project = Project::query()
            ->where('team_id', $teamId)
            ->whereKey($projectId)
            ->first();

        abort_unless($project !== null, 404, 'Project not found.');

        return $project;
    }

    private function findClientForActorOrFail(int $clientId): Client
    {
        $teamId = $this->currentTeamIdOrFail();

        $client = Client::query()
            ->where('team_id', $teamId)
            ->whereKey($clientId)
            ->first();

        abort_unless($client !== null, 403, 'Client does not belong to this user.');

        return $client;
    }
}
