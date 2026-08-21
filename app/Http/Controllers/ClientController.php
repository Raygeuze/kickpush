<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function indexPage(): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $clients = Client::query()
            ->where('user_id', Auth::id())
            ->with([
                'projects:id,client_id,name,description,is_active,created_at,updated_at',
                'tasks:id,client_id,project_id,name,description,is_active,is_default,created_at,updated_at',
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'currency', 'hourly_rate', 'notes', 'created_at', 'updated_at']);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
        ]);
    }

    public function createPage(): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        return Inertia::render('Clients/Create');
    }

    public function list(): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $clients = Client::query()
            ->where('user_id', Auth::id())
            ->with([
                'projects:id,client_id,name,description,is_active,created_at,updated_at',
                'tasks:id,client_id,project_id,name,description,is_active,is_default,created_at,updated_at',
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'currency', 'hourly_rate', 'notes', 'created_at', 'updated_at']);

        return response()->json([
            'clients' => $clients,
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => 'nullable|string|max:2000',
        ]);

        $client = Client::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'currency' => Str::upper($validated['currency']),
            'hourly_rate' => (float) $validated['hourly_rate'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Client created.',
            'client' => $client,
        ], 201);
    }

    public function update(Request $request, int $clientId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $client = Client::query()
            ->where('user_id', Auth::id())
            ->whereKey($clientId)
            ->first();

        if (!$client) {
            return response()->json([
                'message' => 'Client not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => 'nullable|string|max:2000',
        ]);

        $client->name = $validated['name'];
        $client->email = $validated['email'] ?? null;
        $client->currency = Str::upper($validated['currency']);
        $client->hourly_rate = (float) $validated['hourly_rate'];
        $client->notes = $validated['notes'] ?? null;
        $client->save();

        return response()->json([
            'message' => 'Client updated.',
            'client' => $client,
        ]);
    }
}
