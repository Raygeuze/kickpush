<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function list(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);

        $query = Project::query()
            ->where('user_id', Auth::id())
            ->with('client:id,name')
            ->orderBy('name');

        if (isset($validated['client_id'])) {
            $query->where('client_id', (int) $validated['client_id']);
        }

        return response()->json([
            'projects' => $query->get(),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $client = $this->findClientForActorOrFail((int) $validated['client_id']);
        $projectName = trim((string) $validated['name']);

        if ($projectName === '') {
            return response()->json([
                'message' => 'Project name is required.',
            ], 422);
        }

        $nameExists = Project::query()
            ->where('client_id', $client->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)])
            ->exists();

        if ($nameExists) {
            return response()->json([
                'message' => 'A project with this name already exists for this client.',
            ], 422);
        }

        $project = Project::create([
            'user_id' => Auth::id(),
            'client_id' => $client->id,
            'name' => $projectName,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Project created.',
            'project' => $project->load('client:id,name'),
        ], 201);
    }

    public function update(Request $request, int $projectId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $project = $this->findProjectForActorOrFail($projectId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'sometimes|boolean',
        ]);

        if (array_key_exists('name', $validated)) {
            $projectName = trim((string) $validated['name']);

            if ($projectName === '') {
                return response()->json([
                    'message' => 'Project name is required.',
                ], 422);
            }

            $nameExists = Project::query()
                ->where('client_id', $project->client_id)
                ->where('id', '!=', $project->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)])
                ->exists();

            if ($nameExists) {
                return response()->json([
                    'message' => 'A project with this name already exists for this client.',
                ], 422);
            }

            $project->name = $projectName;
        }

        if (array_key_exists('description', $validated)) {
            $project->description = $validated['description'];
        }

        if (array_key_exists('is_active', $validated)) {
            if ((bool) $validated['is_active'] === false) {
                $hasActiveTasks = $project->tasks()
                    ->where('is_active', true)
                    ->exists();

                if ($hasActiveTasks) {
                    return response()->json([
                        'message' => 'Archive active tasks in this project before archiving the project.',
                    ], 422);
                }
            }

            $project->is_active = (bool) $validated['is_active'];
        }

        $project->save();

        return response()->json([
            'message' => 'Project updated.',
            'project' => $project->fresh()->load('client:id,name'),
        ]);
    }

    public function destroy(int $projectId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $project = $this->findProjectForActorOrFail($projectId);

        $hasTasks = $project->tasks()->exists();

        if ($hasTasks) {
            return response()->json([
                'message' => 'Delete or move all tasks from this project before deleting it.',
            ], 422);
        }

        $project->delete();

        return response()->json([
            'message' => 'Project deleted.',
        ]);
    }

    private function findProjectForActorOrFail(int $projectId): Project
    {
        $project = Project::query()
            ->where('user_id', Auth::id())
            ->whereKey($projectId)
            ->first();

        abort_unless($project !== null, 404, 'Project not found.');

        return $project;
    }

    private function findClientForActorOrFail(int $clientId): Client
    {
        $client = Client::query()
            ->where('user_id', Auth::id())
            ->whereKey($clientId)
            ->first();

        abort_unless($client !== null, 403, 'Client does not belong to this user.');

        return $client;
    }
}
