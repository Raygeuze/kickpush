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

        $session = $this->findActiveSession();
        $isRunning = $session !== null && $session->paused_at === null;
        $isPaused = $session !== null && $session->paused_at !== null;

        return response()->json([
            'running' => $isRunning,
            'paused' => $isPaused,
            'active' => $session !== null,
            'elapsed_seconds' => $this->calculateElapsedSeconds($session),
            'session' => $session,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $existing = $this->findActiveSession();

        if ($existing) {
            return response()->json([
                'message' => $existing->paused_at ? 'Timer is paused. Resume it to continue.' : 'Timer already running.',
                'session' => $existing,
            ], 409);
        }

        $session = TimerSession::create([
            'user_id' => Auth::id(),
            'started_at' => now(),
            'accumulated_seconds' => 0,
        ]);

        return response()->json([
            'message' => 'Timer started.',
            'session' => $session,
        ], 201);
    }

    public function pause(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findRunningSession();

        if (!$session) {
            $pausedSession = $this->findPausedSession();

            if ($pausedSession) {
                return response()->json([
                    'message' => 'Timer is already paused.',
                    'session' => $pausedSession,
                ], 200);
            }

            return response()->json([
                'message' => 'No running timer found.',
            ], 404);
        }

        $pausedAt = now();
        $elapsedSinceStart = (int) floor($session->started_at->diffInSeconds($pausedAt));
        $session->accumulated_seconds = (int) ($session->accumulated_seconds ?? 0)
            + max(0, $elapsedSinceStart);
        $session->paused_at = $pausedAt;
        $session->save();

        return response()->json([
            'message' => 'Timer paused.',
            'session' => $session,
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findPausedSession();

        if (!$session) {
            $runningSession = $this->findRunningSession();

            if ($runningSession) {
                return response()->json([
                    'message' => 'Timer is already running.',
                    'session' => $runningSession,
                ], 200);
            }

            return response()->json([
                'message' => 'No paused timer found.',
            ], 404);
        }

        $session->started_at = now();
        $session->paused_at = null;
        $session->save();

        return response()->json([
            'message' => 'Timer resumed.',
            'session' => $session,
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findActiveSession();

        if (!$session) {
            return response()->json([
                'message' => 'No active timer found.',
            ], 404);
        }

        $stoppedAt = now();
        $session->stopped_at = $stoppedAt;
        $session->duration_seconds = $this->calculateElapsedSeconds($session, $stoppedAt);
        $session->paused_at = null;
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
            ->whereNull('paused_at')
            ->latest('started_at')
            ->first();
    }

    private function findPausedSession(): ?TimerSession
    {
        return $this->applyActorScope(TimerSession::query())
            ->whereNull('stopped_at')
            ->whereNotNull('paused_at')
            ->latest('paused_at')
            ->first();
    }

    private function findActiveSession(): ?TimerSession
    {
        return $this->applyActorScope(TimerSession::query())
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();
    }

    private function calculateElapsedSeconds(?TimerSession $session, $at = null): int
    {
        if (!$session) {
            return 0;
        }

        $referenceTime = $at ?? now();
        $accumulated = (int) ($session->accumulated_seconds ?? 0);

        if ($session->paused_at !== null) {
            return $accumulated;
        }

        $elapsedSinceStart = (int) floor($session->started_at->diffInSeconds($referenceTime));

        return $accumulated + max(0, $elapsedSinceStart);
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
