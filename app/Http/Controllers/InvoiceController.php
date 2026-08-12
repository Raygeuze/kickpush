<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\TimerSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'invoice_number' => 'nullable|integer|min:1|unique:invoices,invoice_number',
            'client_id' => 'nullable|integer|exists:clients,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $clientId = $validated['client_id'] ?? null;

        if ($clientId) {
            $this->findClientForActorOrFail((int) $clientId);
        }

        $providedInvoiceNumber = array_key_exists('invoice_number', $validated)
            ? (string) ((int) $validated['invoice_number'])
            : null;

        $invoice = Invoice::create([
            'user_id' => Auth::id(),
            'client_id' => $clientId,
            'invoice_number' => $providedInvoiceNumber ?? $this->generateTemporaryInvoiceNumber(),
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($providedInvoiceNumber === null) {
            $invoice->invoice_number = (string) $invoice->id;
            $invoice->save();
        }

        return response()->json([
            'message' => 'Invoice created.',
            'invoice' => $this->formatInvoice($invoice->fresh()),
        ], 201);
    }

    public function latest(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->applyActorScope(Invoice::query())
            ->with('client')
            ->latest('created_at')
            ->first();

        return response()->json([
            'invoice' => $invoice ? $this->formatInvoice($invoice) : null,
        ]);
    }

    public function index(Request $request): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);

        $selectedClientId = $validated['client_id'] ?? null;

        if ($selectedClientId) {
            $this->findClientForActorOrFail((int) $selectedClientId);
        }

        $clients = Client::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $invoicesQuery = $this->applyActorScope(Invoice::query())
            ->with('client')
            ->latest('created_at');

        if ($selectedClientId) {
            $invoicesQuery->where('client_id', (int) $selectedClientId);
        }

        $invoices = $invoicesQuery->get();

        return Inertia::render('Invoices/Index', [
            'clients' => $clients,
            'selectedClientId' => $selectedClientId,
            'invoices' => $invoices->map(fn (Invoice $invoice) => $this->formatInvoice($invoice))->values(),
        ]);
    }

    public function show(int $invoiceId): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        return Inertia::render('Invoices/Show', [
            'invoice' => $this->formatInvoice($invoice),
            'assignedSessions' => $this->assignedSessionsForInvoice($invoice),
            'availableSessions' => $this->availableConfirmedSessions($invoice),
            'expenses' => $this->invoiceExpenses($invoice),
            'summary' => $this->invoiceSummary($invoice),
        ]);
    }

    public function details(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        return response()->json([
            'invoice' => $this->formatInvoice($invoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($invoice),
            'available_sessions' => $this->availableConfirmedSessions($invoice),
            'expenses' => $this->invoiceExpenses($invoice),
            'summary' => $this->invoiceSummary($invoice),
        ]);
    }

    public function attachSession(Request $request, int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $validated = $request->validate([
            'session_id' => 'required|integer|exists:timer_sessions,id',
        ]);

        $session = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereKey((int) $validated['session_id'])
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session not found for this user.',
            ], 404);
        }

        if ($session->stopped_at === null) {
            return response()->json([
                'message' => 'Only stopped timer sessions can be assigned to an invoice.',
            ], 422);
        }

        if ($session->invoice_id !== null && (int) $session->invoice_id !== (int) $invoice->id) {
            return response()->json([
                'message' => 'This timer session is already assigned to another invoice.',
            ], 422);
        }

        $session->invoice_id = $invoice->id;
        $session->save();

        return response()->json([
            'message' => 'Timer session added to invoice.',
            'invoice' => $this->formatInvoice($invoice->fresh()),
            'assigned_sessions' => $this->assignedSessionsForInvoice($invoice->fresh()),
            'available_sessions' => $this->availableConfirmedSessions($invoice->fresh()),
            'expenses' => $this->invoiceExpenses($invoice->fresh()),
            'summary' => $this->invoiceSummary($invoice->fresh()),
        ]);
    }

    public function inlineTimerStatus(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $runningSession = $this->findAnyRunningSessionForActor();

        if (!$runningSession) {
            return response()->json([
                'running' => false,
                'session' => null,
            ]);
        }

        if ((int) $runningSession->invoice_id !== (int) $invoice->id) {
            return response()->json([
                'running' => false,
                'session' => null,
                'message' => 'A timer is currently running on another invoice. Stop it before starting this one.',
            ]);
        }

        return response()->json([
            'running' => true,
            'session' => $runningSession,
        ]);
    }

    public function startInlineTimer(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $runningSession = $this->findAnyRunningSessionForActor();

        if ($runningSession) {
            if ((int) $runningSession->invoice_id === (int) $invoice->id) {
                return response()->json([
                    'message' => 'Timer is already running for this invoice.',
                    'session' => $runningSession,
                ]);
            }

            return response()->json([
                'message' => 'A timer is currently running on another invoice. Stop it before starting this one.',
            ], 422);
        }

        $session = TimerSession::create([
            'user_id' => Auth::id(),
            'invoice_id' => $invoice->id,
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Timer started for this invoice.',
            'session' => $session,
        ], 201);
    }

    public function stopInlineTimer(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $session = $this->applyActorScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'No active timer found for this invoice.',
            ], 404);
        }

        $stoppedAt = now();
        $session->stopped_at = $stoppedAt;
        $session->duration_seconds = $session->started_at->diffInSeconds($stoppedAt);
        $session->save();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Timer stopped for this invoice.',
            'session' => $session,
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function createManualSession(Request $request, int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $validated = $request->validate([
            'duration_minutes' => 'required|integer|min:1|max:1440',
            'started_at' => 'nullable|date',
        ]);

        $durationSeconds = ((int) $validated['duration_minutes']) * 60;
        $startedAt = isset($validated['started_at']) ? now()->parse($validated['started_at']) : now();
        $stoppedAt = (clone $startedAt)->addSeconds($durationSeconds);

        TimerSession::create([
            'user_id' => Auth::id(),
            'invoice_id' => $invoice->id,
            'started_at' => $startedAt,
            'stopped_at' => $stoppedAt,
            'duration_seconds' => $durationSeconds,
        ]);

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Manual timer session created and added to invoice.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function detachSession(int $invoiceId, int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $session = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereKey($sessionId)
            ->where('invoice_id', $invoice->id)
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session is not assigned to this invoice.',
            ], 404);
        }

        $session->invoice_id = null;
        $session->save();

        return response()->json([
            'message' => 'Timer session removed from invoice.',
            'invoice' => $this->formatInvoice($invoice->fresh()),
            'assigned_sessions' => $this->assignedSessionsForInvoice($invoice->fresh()),
            'available_sessions' => $this->availableConfirmedSessions($invoice->fresh()),
            'expenses' => $this->invoiceExpenses($invoice->fresh()),
            'summary' => $this->invoiceSummary($invoice->fresh()),
        ]);
    }

    public function finalize(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        $stoppedRunningSession = null;

        $runningSession = $this->findAnyRunningSessionForActor();

        if ($runningSession) {
            $stoppedAt = now();
            $runningSession->invoice_id = $invoice->id;
            $runningSession->stopped_at = $stoppedAt;
            $runningSession->duration_seconds = $runningSession->started_at->diffInSeconds($stoppedAt);
            $runningSession->save();

            $stoppedRunningSession = $runningSession;
        }

        if ($invoice->status !== 'finalized') {
            $invoice->status = 'finalized';
            $invoice->issued_at = $invoice->issued_at ?? now();
            $invoice->save();
        }

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => $stoppedRunningSession
                ? 'Invoice finalized. Running timer was stopped and attached to this invoice.'
                : 'Invoice finalized. It is now locked from edits.',
            'stopped_running_session' => $stoppedRunningSession,
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function markPaid(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        if ($invoice->status === 'paid') {
            $freshInvoice = $invoice->fresh();

            return response()->json([
                'message' => 'Invoice is already marked as paid.',
                'invoice' => $this->formatInvoice($freshInvoice),
                'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
                'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
                'expenses' => $this->invoiceExpenses($freshInvoice),
                'summary' => $this->invoiceSummary($freshInvoice),
            ]);
        }

        if ($invoice->status !== 'finalized') {
            return response()->json([
                'message' => 'Invoice must be finalized before it can be marked as paid.',
            ], 422);
        }

        $invoice->status = 'paid';
        $invoice->paid_at = now();
        $invoice->save();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Invoice marked as paid.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function addExpense(Request $request, int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $invoice->expenses()->create([
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
        ]);

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Expense added to invoice.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function removeExpense(int $invoiceId, int $expenseId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $expense = Expense::query()
            ->where('invoice_id', $invoice->id)
            ->whereKey($expenseId)
            ->first();

        if (!$expense) {
            return response()->json([
                'message' => 'Expense not found for this invoice.',
            ], 404);
        }

        $expense->delete();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Expense removed from invoice.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function downloadPdf(int $invoiceId)
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        if ($invoice->status !== 'finalized') {
            return response()->json([
                'message' => 'Only finalized invoices can be exported as PDF.',
            ], 422);
        }

        $user = Auth::user();
        $hourlyRate = $user ? (float) $user->hourly_rate : 0;
        $sessions = $this->assignedSessionsForInvoice($invoice);
        $expenses = $this->invoiceExpenses($invoice);

        $totalDurationSeconds = (int) $sessions->sum(function (TimerSession $session) {
            return (int) ($session->duration_seconds ?? 0);
        });
        $totalHours = round($totalDurationSeconds / 3600, 2);
        $timeAmount = round($totalHours * $hourlyRate, 2);

        $expenseLines = $expenses->map(function (Expense $expense): array {
            return [
                'label' => $expense->name ?: 'One-off expense',
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
            ];
        })->values()->all();

        $lineItems = array_merge([
            [
                'label' => 'Billable time',
                'description' => $totalHours . ' hours @ $' . number_format($hourlyRate, 2) . '/hr',
                'amount' => $timeAmount,
            ],
        ], $expenseLines);

        $grandTotal = array_reduce($lineItems, function (float $carry, array $line): float {
            return $carry + (float) $line['amount'];
        }, 0.0);

        $generatedAt = now();
        $dueDate = $generatedAt->copy()->addDays(14);

        $invoice->due_at = $dueDate;
        $invoice->save();

        if (!app()->bound('dompdf.wrapper')) {
            return response()->json([
                'message' => 'PDF engine is not installed. Run composer install/update to include barryvdh/laravel-dompdf.',
            ], 500);
        }

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('invoices.pdf', [
            'invoice' => $invoice,
            'user' => $user,
            'lineItems' => $lineItems,
            'totalDurationSeconds' => $totalDurationSeconds,
            'hourlyRate' => $hourlyRate,
            'grandTotal' => round($grandTotal, 2),
            'generatedAt' => $generatedAt,
            'dueDate' => $dueDate,
        ]);

        return $pdf->download('invoice-INV' . $invoice->id . '.pdf');
    }

    private function applyActorScope(Builder $query): Builder
    {
        return $query->where('user_id', Auth::id());
    }

    private function applyActorScopeToSessions(Builder $query): Builder
    {
        return $query->where('user_id', Auth::id());
    }

    private function findInvoiceForActorOrFail(int $invoiceId): Invoice
    {
        $invoice = $this->applyActorScope(Invoice::query())
            ->with('client')
            ->whereKey($invoiceId)
            ->first();

        abort_unless($invoice !== null, 404, 'Invoice not found.');

        return $invoice;
    }

    private function abortIfInvoiceFinalized(Invoice $invoice): void
    {
        abort_if(in_array($invoice->status, ['finalized', 'paid'], true), 422, 'Finalized or paid invoices cannot be edited.');
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

    private function findAnyRunningSessionForActor(): ?TimerSession
    {
        return $this->applyActorScopeToSessions(TimerSession::query())
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();
    }

    private function assignedSessionsForInvoice(Invoice $invoice)
    {
        return $this->applyActorScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('stopped_at')
            ->orderByDesc('started_at')
            ->get();
    }

    private function availableConfirmedSessions(Invoice $invoice)
    {
        return $this->applyActorScopeToSessions(TimerSession::query())
            ->whereNotNull('stopped_at')
            ->whereNull('invoice_id')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();
    }

    private function invoiceExpenses(Invoice $invoice)
    {
        return Expense::query()
            ->where('invoice_id', $invoice->id)
            ->orderByDesc('created_at')
            ->get();
    }

    private function invoiceSummary(Invoice $invoice): array
    {
        $totals = $this->applyActorScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('stopped_at')
            ->selectRaw('COUNT(*) as sessions_count, COALESCE(SUM(duration_seconds), 0) as total_duration_seconds')
            ->first();

        $sessionsCount = $totals ? (int) $totals->sessions_count : 0;
        $totalDurationSeconds = $totals ? (int) $totals->total_duration_seconds : 0;
        $totalExpensesAmount = (float) (Expense::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount'));
        $hourlyRate = (float) (Auth::user()->hourly_rate ?? 0);
        $billableTimeAmount = round(($totalDurationSeconds / 3600) * $hourlyRate, 2);
        $totalBillableAmount = round($billableTimeAmount + $totalExpensesAmount, 2);

        return [
            'sessions_count' => $sessionsCount,
            'total_duration_seconds' => $totalDurationSeconds,
            'total_expenses_amount' => $totalExpensesAmount,
            'billable_time_amount' => $billableTimeAmount,
            'total_billable_amount' => $totalBillableAmount,
        ];
    }

    private function formatInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing('client');

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $invoice->client_id,
            'client' => $invoice->client ? [
                'id' => $invoice->client->id,
                'name' => $invoice->client->name,
                'email' => $invoice->client->email,
                'notes' => $invoice->client->notes,
            ] : null,
            'status' => $invoice->status,
            'issued_at' => $invoice->issued_at,
            'due_at' => $invoice->due_at,
            'paid_at' => $invoice->paid_at,
            'notes' => $invoice->notes,
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
            'is_finalized' => in_array($invoice->status, ['finalized', 'paid'], true),
        ];
    }

    private function generateTemporaryInvoiceNumber(): string
    {
        return 'TMP-' . (string) Str::uuid();
    }
}
