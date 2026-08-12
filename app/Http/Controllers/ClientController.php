<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function createPage(): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        return Inertia::render('Clients/Create');
    }

    public function index(): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $clients = Client::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

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
            'notes' => 'nullable|string|max:2000',
        ]);

        $client = Client::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Client created.',
            'client' => $client,
        ], 201);
    }
}
