<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\TimerSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimerSessionController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'confirmed_only' => 'nullable|boolean',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
        ]);

        $limit = $validated['limit'] ?? 10;
        $confirmedOnly = (bool) ($validated['confirmed_only'] ?? false);
        $invoiceId = $validated['invoice_id'] ?? null;

        $query = $this->applyActorScope(TimerSession::query());

        if ($confirmedOnly) {
            $query->whereNotNull('invoice_id');
        }

        if ($invoiceId) {
            $this->assertInvoiceBelongsToActor((int) $invoiceId);
            $query->where('invoice_id', $invoiceId);
        }

        $sessions = $query
            ->latest('started_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findRunningSession();

        return response()->json([
            'running' => $session !== null,
            'session' => $session,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $existing = $this->findRunningSession();

        if ($existing) {
            return response()->json([
                'message' => 'Timer already running.',
                'session' => $existing,
            ], 200);
        }

        $session = TimerSession::create([
            'user_id' => Auth::id(),
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Timer started.',
            'session' => $session,
        ], 201);
    }

    public function stop(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findRunningSession();

        if (!$session) {
            return response()->json([
                'message' => 'No active timer found.',
            ], 404);
        }

        $stoppedAt = now();
        $session->stopped_at = $stoppedAt;
        $session->duration_seconds = $session->started_at->diffInSeconds($stoppedAt);
        $session->save();

        return response()->json([
            'message' => 'Timer stopped.',
            'session' => $session,
        ]);
    }

    public function submitToInvoice(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'session_id' => 'required|integer|exists:timer_sessions,id',
            'invoice_id' => 'required|integer|exists:invoices,id',
        ]);

        $invoice = $this->applyActorScope(Invoice::query())
            ->whereKey((int) $validated['invoice_id'])
            ->first();

        if (!$invoice) {
            abort(403, 'Invoice does not belong to this user.');
        }

        if ($invoice->status === 'finalized') {
            return response()->json([
                'message' => 'Finalized invoices cannot receive new timer sessions.',
            ], 422);
        }

        $session = $this->applyActorScope(TimerSession::query())
            ->whereKey((int) $validated['session_id'])
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session not found for this user.',
            ], 404);
        }

        if ($session->stopped_at === null) {
            return response()->json([
                'message' => 'Stop the timer session before submitting it to an invoice.',
            ], 422);
        }

        $session->invoice_id = (int) $validated['invoice_id'];
        $session->save();

        return response()->json([
            'message' => 'Timer session submitted to invoice.',
            'session' => $session,
        ]);
    }

    private function findRunningSession(): ?TimerSession
    {
        return $this->applyActorScope(TimerSession::query())
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();
    }

    private function applyActorScope(Builder $query): Builder
    {
        return $query->where('user_id', Auth::id());
    }

    private function assertInvoiceBelongsToActor(int $invoiceId): void
    {
        $invoiceQuery = $this->applyActorScope(Invoice::query())
            ->whereKey($invoiceId);

        abort_unless($invoiceQuery->exists(), 403, 'Invoice does not belong to this user.');
    }
}
