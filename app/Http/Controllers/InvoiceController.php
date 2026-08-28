<?php

namespace App\Http\Controllers;

use App\Mail\FinalizedInvoiceMail;
use App\Models\BusinessExpense;
use App\Models\Client;
use App\Models\Expense;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\TimerSession;
use App\Models\UserAdditionalTax;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InvoiceController extends Controller
{
    private function currentTeamIdOrFail(): int
    {
        $user = Auth::user();

        abort_unless($user && $user->currentTeam, 403, 'Select a team to continue.');

        return (int) $user->currentTeam->id;
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $userId = (int) Auth::id();
        $teamId = $this->currentTeamIdOrFail();
        $currentFinancialYear = $this->findOrCreateFinancialYearForTeam($userId, $teamId, $this->defaultNzFinancialYearStart());

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
            'user_id' => $userId,
            'team_id' => $teamId,
            'client_id' => $clientId,
            'financial_year_id' => $currentFinancialYear->id,
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
            'financial_year_id' => 'nullable|integer|exists:financial_years,id',
        ]);

        $selectedClientId = $validated['client_id'] ?? null;
        $currentFinancialYear = $this->findOrCreateFinancialYearForTeam((int) Auth::id(), $this->currentTeamIdOrFail(), $this->defaultNzFinancialYearStart());
        $financialYears = $this->financialYearsForActor();
        $selectedFinancialYearId = isset($validated['financial_year_id'])
            ? (int) $validated['financial_year_id']
            : null;

        if ($selectedFinancialYearId === null) {
            $selectedFinancialYearId = (int) $currentFinancialYear->id;
        }

        $selectedFinancialYear = $financialYears->firstWhere('id', $selectedFinancialYearId);

        abort_unless($selectedFinancialYear !== null, 403, 'Selected financial year does not belong to this user.');

        if ($selectedClientId) {
            $this->findClientForActorOrFail((int) $selectedClientId);
        }

        $clients = Client::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'currency', 'hourly_rate']);

        $invoicesQuery = $this->applyActorScope(Invoice::query())
            ->with(['client', 'financialYear'])
            ->where('financial_year_id', $selectedFinancialYear->id)
            ->latest('created_at');

        if ($selectedClientId) {
            $invoicesQuery->where('client_id', (int) $selectedClientId);
        }

        $invoices = $invoicesQuery->get();
        $clientYearSummaries = $this->indexClientFinancialYearSummaries($selectedFinancialYear, $selectedClientId);

        return Inertia::render('Invoices/Index', [
            'clients' => $clients,
            'selectedClientId' => $selectedClientId,
            'financialYears' => $financialYears->map(fn (FinancialYear $financialYear) => [
                'id' => $financialYear->id,
                'label' => $financialYear->label,
                'start_year' => $financialYear->start_year,
                'end_year' => $financialYear->end_year,
            ])->values(),
            'currentFinancialYearId' => $currentFinancialYear->id,
            'selectedFinancialYearId' => $selectedFinancialYear->id,
            'selectedFinancialYearLabel' => $selectedFinancialYear->label,
            'clientYearSummaries' => $clientYearSummaries,
            'invoices' => $invoices->map(fn (Invoice $invoice) => $this->formatInvoice($invoice))->values(),
        ]);
    }

    private function indexClientFinancialYearSummaries(FinancialYear $financialYear, ?int $selectedClientId): array
    {
        $invoicesQuery = $this->applyActorScope(Invoice::query())
            ->with('client:id,name,currency,hourly_rate')
            ->where('financial_year_id', $financialYear->id);

        if ($selectedClientId) {
            $invoicesQuery->where('client_id', $selectedClientId);
        }

        $invoices = $invoicesQuery->get([
            'id',
            'client_id',
            'status',
            'due_at',
        ]);

        if ($invoices->isEmpty()) {
            return [];
        }

        $invoiceIds = $invoices->pluck('id')->all();
        $clientRatesByInvoice = $invoices->mapWithKeys(fn (Invoice $invoice): array => [
            (int) $invoice->id => $invoice->client ? (float) $invoice->client->hourly_rate : 0.0,
        ])->all();

        $sessionTotals = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNotNull('stopped_at')
            ->selectRaw('invoice_id, user_id, COALESCE(SUM(duration_seconds), 0) as total_duration_seconds')
            ->groupBy('invoice_id', 'user_id')
            ->get();

        $billableTimeByInvoice = $this->calculateBillableTimeByInvoiceFromSessionRows($sessionTotals, $clientRatesByInvoice);

        $summaries = [];

        foreach ($invoices as $invoice) {
            $clientId = $invoice->client_id ? (int) $invoice->client_id : 0;

            if (!array_key_exists($clientId, $summaries)) {
                $summaries[$clientId] = [
                    'client_id' => $invoice->client_id,
                    'client_name' => $invoice->client ? (string) $invoice->client->name : 'Unassigned Client',
                    'currency' => $this->normalizeCurrencyCode($invoice->client ? (string) $invoice->client->currency : null) ?? 'USD',
                    'paid' => [
                        'invoice_count' => 0,
                        'billable_time_amount' => 0.0,
                    ],
                    'sent_not_paid' => [
                        'invoice_count' => 0,
                        'billable_time_amount' => 0.0,
                    ],
                    'draft' => [
                        'invoice_count' => 0,
                        'billable_time_amount' => 0.0,
                    ],
                    'overdue' => [
                        'invoice_count' => 0,
                        'billable_time_amount' => 0.0,
                    ],
                ];
            }

            $billableTimeAmount = (float) ($billableTimeByInvoice[(int) $invoice->id] ?? 0.0);

            $bucket = 'draft';

            if ($invoice->status === 'paid') {
                $bucket = 'paid';
            } elseif ($invoice->status === 'finalized') {
                $bucket = 'sent_not_paid';
            }

            $summaries[$clientId][$bucket]['invoice_count'] += 1;
            $summaries[$clientId][$bucket]['billable_time_amount'] += $billableTimeAmount;

            if ($invoice->status !== 'paid' && $invoice->due_at !== null && $invoice->due_at->lt(now())) {
                $summaries[$clientId]['overdue']['invoice_count'] += 1;
                $summaries[$clientId]['overdue']['billable_time_amount'] += $billableTimeAmount;
            }
        }

        return collect($summaries)
            ->map(function (array $summary): array {
                foreach (['paid', 'sent_not_paid', 'draft', 'overdue'] as $bucket) {
                    $summary[$bucket]['billable_time_amount'] = round((float) $summary[$bucket]['billable_time_amount'], 2);
                }

                return $summary;
            })
            ->sortBy(fn (array $summary): string => strtolower((string) ($summary['client_name'] ?? '')))
            ->values()
            ->all();
    }

    public function show(int $invoiceId): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        return Inertia::render('Invoices/Show', [
            'invoice' => $this->formatInvoice($invoice),
            'financialYears' => $this->financialYearsForActor()->map(fn (FinancialYear $financialYear) => [
                'id' => $financialYear->id,
                'label' => $financialYear->label,
                'start_year' => $financialYear->start_year,
                'end_year' => $financialYear->end_year,
            ])->values(),
            'clientTasks' => $this->clientTasksForInvoice($invoice),
            'assignedSessions' => $this->assignedSessionsForInvoice($invoice),
            'availableSessions' => $this->availableConfirmedSessions($invoice),
            'expenses' => $this->invoiceExpenses($invoice),
            'summary' => $this->invoiceSummary($invoice),
        ]);
    }

    public function taxSummary(int $invoiceId): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');
        $this->abortUnlessCurrentTeamOwner();

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $summary = $this->invoiceSummary($invoice);
        $baseCurrency = $this->normalizeCurrencyCode($invoice->conversion_source_currency)
            ?? $this->normalizeCurrencyCode($invoice->client ? (string) $invoice->client->currency : null)
            ?? 'USD';
        $taxSummary = $this->calculateTaxSummary($summary, $baseCurrency);
        $currencyConversion = $this->buildWiseCurrencyConversion($invoice, $taxSummary);

        return Inertia::render('Invoices/TaxSummary', [
            'invoice' => $this->formatInvoice($invoice),
            'summary' => $summary,
            'taxSummary' => $taxSummary,
            'currencyConversion' => $currencyConversion,
        ]);
    }

    public function financialYearTaxSummary(Request $request): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');
        $this->abortUnlessCurrentTeamOwner();

        $validated = $request->validate([
            'financial_year_start' => 'nullable|integer|min:2000|max:9999',
        ]);

        $financialYearStart = isset($validated['financial_year_start'])
            ? (int) $validated['financial_year_start']
            : $this->defaultNzFinancialYearStart();
        $financialYear = $this->findOrCreateFinancialYearForTeam((int) Auth::id(), $this->currentTeamIdOrFail(), $financialYearStart);
        $summary = $this->financialYearInvoiceSummary($financialYear);
        $convertedTaxSummary = $this->financialYearConvertedTaxSummary($financialYear, $summary);

        return Inertia::render('Invoices/FinancialYearTaxSummary', [
            'financialYearStart' => $financialYearStart,
            'financialYearLabel' => $financialYear->label,
            'periodStart' => $financialYear->start_date->toDateString(),
            'periodEnd' => $financialYear->end_date->toDateString(),
            'summary' => $summary,
            'convertedTaxSummary' => $convertedTaxSummary,
        ]);
    }

    public function assignFinancialYear(Request $request, int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        $validated = $request->validate([
            'financial_year_id' => 'required|integer|exists:financial_years,id',
        ]);

        $financialYear = $this->findFinancialYearForActorOrFail((int) $validated['financial_year_id']);

        $invoice->financial_year_id = $financialYear->id;
        $invoice->save();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Invoice financial year updated.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
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
        $activeSession = $this->findAnyActiveSessionForActor();

        if (!$activeSession) {
            return response()->json([
                'running' => false,
                'paused' => false,
                'active' => false,
                'elapsed_seconds' => 0,
                'session' => null,
            ]);
        }

        if ((int) $activeSession->invoice_id !== (int) $invoice->id) {
            $otherState = $activeSession->paused_at ? 'paused' : 'running';

            return response()->json([
                'running' => false,
                'paused' => false,
                'active' => false,
                'elapsed_seconds' => 0,
                'session' => null,
                'message' => "A timer is currently {$otherState} on another invoice. Stop it before starting this one.",
            ]);
        }

        return response()->json([
            'running' => $activeSession->paused_at === null,
            'paused' => $activeSession->paused_at !== null,
            'active' => true,
            'elapsed_seconds' => $this->calculateElapsedSeconds($activeSession),
            'session' => $activeSession,
        ]);
    }

    public function startInlineTimer(Request $request, int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $validated = $request->validate([
            'project_id' => 'nullable|integer',
            'task_id' => 'nullable|integer',
        ]);

        $taskId = $this->resolveTaskIdForInvoiceClient(
            $invoice,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            isset($validated['task_id']) ? (int) $validated['task_id'] : null
        );

        $activeSession = $this->findAnyActiveSessionForActor();

        if ($activeSession) {
            if ((int) $activeSession->invoice_id === (int) $invoice->id) {
                return response()->json([
                    'message' => $activeSession->paused_at
                        ? 'Timer is paused for this invoice. Resume it to continue.'
                        : 'Timer is already running for this invoice.',
                    'session' => $activeSession,
                ], 409);
            }

            $otherState = $activeSession->paused_at ? 'paused' : 'running';

            return response()->json([
                'message' => "A timer is currently {$otherState} on another invoice. Stop it before starting this one.",
            ], 422);
        }

        $session = TimerSession::create([
            'user_id' => Auth::id(),
            'team_id' => $this->currentTeamIdOrFail(),
            'invoice_id' => $invoice->id,
            'task_id' => $taskId,
            'started_at' => now(),
            'accumulated_seconds' => 0,
        ]);

        return response()->json([
            'message' => 'Timer started for this invoice.',
            'session' => $session,
        ], 201);
    }

    public function pauseInlineTimer(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $session = $this->applyCurrentUserScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->whereNull('stopped_at')
            ->whereNull('paused_at')
            ->latest('started_at')
            ->first();

        if (!$session) {
            $pausedSession = $this->applyCurrentUserScopeToSessions(TimerSession::query())
                ->where('invoice_id', $invoice->id)
                ->whereNull('stopped_at')
                ->whereNotNull('paused_at')
                ->latest('paused_at')
                ->first();

            if ($pausedSession) {
                return response()->json([
                    'message' => 'Timer is already paused for this invoice.',
                    'session' => $pausedSession,
                ], 200);
            }

            return response()->json([
                'message' => 'No running timer found for this invoice.',
            ], 404);
        }

        $pausedAt = now();
        $elapsedSinceStart = (int) floor($session->started_at->diffInSeconds($pausedAt));
        $session->accumulated_seconds = (int) ($session->accumulated_seconds ?? 0)
            + max(0, $elapsedSinceStart);
        $session->paused_at = $pausedAt;
        $session->save();

        return response()->json([
            'message' => 'Timer paused for this invoice.',
            'session' => $session,
        ]);
    }

    public function resumeInlineTimer(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $session = $this->applyCurrentUserScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->whereNull('stopped_at')
            ->whereNotNull('paused_at')
            ->latest('paused_at')
            ->first();

        if (!$session) {
            $runningSession = $this->applyCurrentUserScopeToSessions(TimerSession::query())
                ->where('invoice_id', $invoice->id)
                ->whereNull('stopped_at')
                ->whereNull('paused_at')
                ->latest('started_at')
                ->first();

            if ($runningSession) {
                return response()->json([
                    'message' => 'Timer is already running for this invoice.',
                    'session' => $runningSession,
                ], 200);
            }

            return response()->json([
                'message' => 'No paused timer found for this invoice.',
            ], 404);
        }

        $session->started_at = now();
        $session->paused_at = null;
        $session->save();

        return response()->json([
            'message' => 'Timer resumed for this invoice.',
            'session' => $session,
        ]);
    }

    public function stopInlineTimer(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $session = $this->applyCurrentUserScopeToSessions(TimerSession::query())
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
        $session->duration_seconds = $this->calculateElapsedSeconds($session, $stoppedAt);
        $session->paused_at = null;
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
            'project_id' => 'nullable|integer',
            'task_id' => 'nullable|integer',
        ]);

        $durationSeconds = ((int) $validated['duration_minutes']) * 60;
        $startedAt = isset($validated['started_at']) ? now()->parse($validated['started_at']) : now();
        $stoppedAt = (clone $startedAt)->addSeconds($durationSeconds);

        $taskId = $this->resolveTaskIdForInvoiceClient(
            $invoice,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            isset($validated['task_id']) ? (int) $validated['task_id'] : null
        );

        TimerSession::create([
            'user_id' => Auth::id(),
            'team_id' => $this->currentTeamIdOrFail(),
            'invoice_id' => $invoice->id,
            'task_id' => $taskId,
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

    public function resumeStoppedSession(int $invoiceId, int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $session = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereKey($sessionId)
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('stopped_at')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Stopped timer session is not assigned to this invoice.',
            ], 404);
        }

        $activeSession = $this->findAnyActiveSessionForActor();

        if ($activeSession) {
            $otherState = $activeSession->paused_at ? 'paused' : 'running';

            return response()->json([
                'message' => "A timer is currently {$otherState} on another session. Stop it before resuming this one.",
            ], 422);
        }

        $session->started_at = now();
        $session->paused_at = null;
        $session->stopped_at = null;
        $session->accumulated_seconds = max(0, (int) ($session->duration_seconds ?? 0));
        $session->duration_seconds = null;
        $session->save();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Stopped session resumed for this invoice.',
            'session' => $session,
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

    public function updateSessionDate(Request $request, int $invoiceId, int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $validated = $request->validate([
            'session_date' => 'required|date',
        ]);

        $session = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereKey($sessionId)
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('stopped_at')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session is not assigned to this invoice.',
            ], 404);
        }

        $newDate = now()->parse($validated['session_date']);

        $session->started_at = $session->started_at
            ? $session->started_at->copy()->setDate($newDate->year, $newDate->month, $newDate->day)
            : $newDate->copy();

        if ($session->stopped_at) {
            $session->stopped_at = $session->stopped_at->copy()->setDate($newDate->year, $newDate->month, $newDate->day);

            if ($session->stopped_at->lessThan($session->started_at)) {
                $session->stopped_at = $session->stopped_at->addDay();
            }
        }

        $session->duration_seconds = $this->calculateElapsedSeconds($session, $session->stopped_at);
        $session->save();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Session date updated.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function updateSessionDuration(Request $request, int $invoiceId, int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $validated = $request->validate([
            'duration_seconds' => 'nullable|integer|min:1|max:604800|required_without:duration_minutes',
            'duration_minutes' => 'nullable|numeric|min:0.01|max:10080|required_without:duration_seconds',
        ]);

        $session = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereKey($sessionId)
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('stopped_at')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session is not assigned to this invoice.',
            ], 404);
        }

        $durationSeconds = isset($validated['duration_seconds'])
            ? (int) $validated['duration_seconds']
            : max(1, (int) round(((float) $validated['duration_minutes']) * 60));

        if ($session->started_at) {
            $session->stopped_at = $session->started_at->copy()->addSeconds($durationSeconds);
        } elseif ($session->stopped_at) {
            $session->started_at = $session->stopped_at->copy()->subSeconds($durationSeconds);
        }

        $session->duration_seconds = $durationSeconds;
        $session->accumulated_seconds = 0;
        $session->paused_at = null;
        $session->save();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Session duration updated.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function updateSessionTask(Request $request, int $invoiceId, int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $validated = $request->validate([
            'project_id' => 'nullable|integer',
            'task_id' => 'nullable|integer',
        ]);

        $session = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereKey($sessionId)
            ->where('invoice_id', $invoice->id)
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session is not assigned to this invoice.',
            ], 404);
        }

        $taskId = $this->resolveTaskIdForInvoiceClient(
            $invoice,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            isset($validated['task_id']) ? (int) $validated['task_id'] : null
        );

        if ($taskId === null) {
            return response()->json([
                'message' => 'Select a valid task for this client before saving.',
            ], 422);
        }

        $session->task_id = $taskId;
        $session->save();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Session task updated.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function updateDiscount(Request $request, int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);
        $this->abortIfInvoiceFinalized($invoice);

        $validated = $request->validate([
            'discount_type' => 'nullable|string|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        $discountType = $validated['discount_type'] ?? null;
        $discountValue = $discountType !== null
            ? round((float) ($validated['discount_value'] ?? 0), 2)
            : 0.0;

        if ($discountType === 'percentage' && $discountValue > 100) {
            return response()->json([
                'message' => 'Percentage discount must be between 0 and 100.',
            ], 422);
        }

        $invoice->discount_type = $discountType;
        $invoice->discount_value = $discountValue;
        $invoice->save();

        $freshInvoice = $invoice->fresh();

        return response()->json([
            'message' => 'Invoice discount updated.',
            'invoice' => $this->formatInvoice($freshInvoice),
            'assigned_sessions' => $this->assignedSessionsForInvoice($freshInvoice),
            'available_sessions' => $this->availableConfirmedSessions($freshInvoice),
            'expenses' => $this->invoiceExpenses($freshInvoice),
            'summary' => $this->invoiceSummary($freshInvoice),
        ]);
    }

    public function finalize(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        $stoppedRunningSession = null;

        $runningSession = $this->findAnyActiveSessionForActor();

        if ($runningSession) {
            $stoppedAt = now();
            $runningSession->invoice_id = $invoice->id;
            $runningSession->stopped_at = $stoppedAt;
            $runningSession->duration_seconds = $this->calculateElapsedSeconds($runningSession, $stoppedAt);
            $runningSession->paused_at = null;
            $runningSession->save();

            $stoppedRunningSession = $runningSession;
        }

        if ($invoice->status !== 'finalized') {
            $invoice->status = 'finalized';
            $invoice->issued_at = $invoice->issued_at ?? now();
            $this->storeFinalizedWiseConversionRate($invoice);
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

    public function destroy(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        if (in_array($invoice->status, ['finalized', 'paid'], true)) {
            return response()->json([
                'message' => 'Finalized or paid invoices cannot be deleted.',
            ], 422);
        }

        // Unassign sessions before deleting so historical session data remains intact.
        $this->applyActorScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->update(['invoice_id' => null]);

        Expense::query()->where('invoice_id', $invoice->id)->delete();
        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted.',
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

        if (!in_array($invoice->status, ['finalized', 'paid'], true)) {
            return response()->json([
                'message' => 'Only finalized or paid invoices can be exported as PDF.',
            ], 422);
        }

        if (!app()->bound('dompdf.wrapper')) {
            return response()->json([
                'message' => 'PDF engine is not installed. Run composer install/update to include barryvdh/laravel-dompdf.',
            ], 500);
        }

        $pdfPayload = $this->buildInvoicePdfPayload($invoice);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('invoices.pdf', [
            'invoice' => $pdfPayload['invoice'],
            'user' => $pdfPayload['user'],
            'team' => $pdfPayload['team'],
            'lineItems' => $pdfPayload['lineItems'],
            'projectTotals' => $pdfPayload['projectTotals'] ?? [],
            'totalDurationSeconds' => $pdfPayload['totalDurationSeconds'],
            'hourlyRate' => $pdfPayload['hourlyRate'],
            'grandTotal' => $pdfPayload['grandTotal'],
            'generatedAt' => $pdfPayload['generatedAt'],
            'dueDate' => $pdfPayload['dueDate'],
        ]);

        return $pdf->download('invoice-INV' . $invoice->id . '.pdf');
    }

    public function emailClientPdf(int $invoiceId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $invoice = $this->findInvoiceForActorOrFail($invoiceId);

        if ($invoice->status !== 'finalized') {
            return response()->json([
                'message' => 'Only finalized invoices can be emailed to clients.',
            ], 422);
        }

        $client = $invoice->client;

        if (!$client || !$client->email || !filter_var($client->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'message' => 'This invoice client does not have a valid email address.',
            ], 422);
        }

        if (!app()->bound('dompdf.wrapper')) {
            return response()->json([
                'message' => 'PDF engine is not installed. Run composer install/update to include barryvdh/laravel-dompdf.',
            ], 500);
        }

        $pdfPayload = $this->buildInvoicePdfPayload($invoice);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('invoices.pdf', [
            'invoice' => $pdfPayload['invoice'],
            'user' => $pdfPayload['user'],
            'team' => $pdfPayload['team'],
            'lineItems' => $pdfPayload['lineItems'],
            'projectTotals' => $pdfPayload['projectTotals'] ?? [],
            'totalDurationSeconds' => $pdfPayload['totalDurationSeconds'],
            'hourlyRate' => $pdfPayload['hourlyRate'],
            'grandTotal' => $pdfPayload['grandTotal'],
            'generatedAt' => $pdfPayload['generatedAt'],
            'dueDate' => $pdfPayload['dueDate'],
        ]);

        $filename = 'invoice-INV' . $invoice->id . '.pdf';

        try {
            Mail::to($client->email)->send(new FinalizedInvoiceMail(
                $pdfPayload['invoice'],
                $client->name,
                $pdfPayload['grandTotal'],
                $pdfPayload['dueDate'],
                $pdf->output(),
                $filename
            ));
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Failed to send invoice email. Please check mail configuration and try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Invoice email sent to ' . $client->email . '.',
        ]);
    }

    private function applyActorScope(Builder $query): Builder
    {
        return $query->where('team_id', $this->currentTeamIdOrFail());
    }

    private function buildInvoicePdfPayload(Invoice $invoice): array
    {
        $freshInvoice = $invoice->fresh(['client', 'financialYear']);
        $user = Auth::user();
        $team = $user ? $user->currentTeam : null;
        $summary = $this->invoiceSummary($freshInvoice);
        $expenses = $this->invoiceExpenses($freshInvoice);
        $projectTotals = $this->invoiceProjectTotals($freshInvoice);

        $totalDurationSeconds = (int) ($summary['total_duration_seconds'] ?? 0);
        $totalHours = round($totalDurationSeconds / 3600, 2);
        $timeAmount = (float) ($summary['billable_time_amount'] ?? 0);
        $effectiveHourlyRate = $totalHours > 0 ? round($timeAmount / $totalHours, 2) : ($freshInvoice->client ? (float) $freshInvoice->client->hourly_rate : 0.0);

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
                'description' => $totalHours . ' hours @ blended $' . number_format($effectiveHourlyRate, 2) . '/hr',
                'amount' => $timeAmount,
            ],
        ], $expenseLines);

        $discountAmount = (float) ($summary['discount_amount'] ?? 0);

        if ($discountAmount > 0) {
            $lineItems[] = [
                'label' => 'Invoice discount',
                'description' => $freshInvoice->discount_type === 'percentage'
                    ? number_format((float) $freshInvoice->discount_value, 2) . '%'
                    : 'Fixed amount',
                'amount' => -$discountAmount,
            ];
        }

        $grandTotal = (float) ($summary['total_billable_amount'] ?? 0);

        $generatedAt = now();
        $dueDate = $freshInvoice->due_at ?: $generatedAt->copy()->addDays(14);

        if (!$freshInvoice->due_at) {
            $freshInvoice->due_at = $dueDate;
            $freshInvoice->save();
            $freshInvoice = $freshInvoice->fresh(['client', 'financialYear']);
        }

        return [
            'invoice' => $freshInvoice,
            'user' => $user,
            'team' => $team,
            'lineItems' => $lineItems,
            'projectTotals' => $projectTotals,
            'totalDurationSeconds' => $totalDurationSeconds,
            'hourlyRate' => $effectiveHourlyRate,
            'grandTotal' => round($grandTotal, 2),
            'generatedAt' => $generatedAt,
            'dueDate' => $dueDate,
        ];
    }

    private function invoiceProjectTotals(Invoice $invoice): array
    {
        $clientHourlyRate = $invoice->client ? (float) $invoice->client->hourly_rate : 0.0;
        $sessions = $this->applyActorScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('stopped_at')
            ->with(['task.project'])
            ->get(['id', 'user_id', 'task_id', 'duration_seconds']);

        $userRateMap = $this->userChargeOutRateMapForIds(
            $sessions->pluck('user_id')->filter()->map(fn ($userId): int => (int) $userId)->unique()->values()->all()
        );

        $grouped = $sessions->groupBy(function (TimerSession $session): string {
            $projectId = optional(optional($session->task)->project)->id;

            return $projectId ? 'project-' . $projectId : 'project-unassigned';
        });

        return $grouped->map(function ($projectSessions, string $groupKey) use ($clientHourlyRate, $userRateMap): array {
            /** @var TimerSession $first */
            $first = $projectSessions->first();
            $project = optional(optional($first)->task)->project;
            $totalDurationSeconds = (int) $projectSessions->sum(fn (TimerSession $session): int => (int) ($session->duration_seconds ?? 0));
            $billableTimeAmount = 0.0;

            foreach ($projectSessions as $session) {
                $durationSeconds = max(0, (int) ($session->duration_seconds ?? 0));
                $sessionUserId = $session->user_id !== null ? (int) $session->user_id : null;
                $hourlyRate = $this->resolveSessionHourlyRate($sessionUserId, $clientHourlyRate, $userRateMap);
                $billableTimeAmount += ($durationSeconds / 3600) * $hourlyRate;
            }

            $billableTimeAmount = round($billableTimeAmount, 2);

            return [
                'project_key' => $groupKey,
                'project_id' => optional($project)->id,
                'project_name' => optional($project)->name ?? 'Unassigned Project',
                'sessions_count' => (int) $projectSessions->count(),
                'total_duration_seconds' => $totalDurationSeconds,
                'billable_time_amount' => $billableTimeAmount,
            ];
        })->sortBy(fn (array $project): string => strtolower((string) ($project['project_name'] ?? '')))
            ->values()
            ->all();
    }

    private function applyActorScopeToSessions(Builder $query): Builder
    {
        return $query->where('team_id', $this->currentTeamIdOrFail());
    }

    private function applyCurrentUserScopeToSessions(Builder $query): Builder
    {
        return $this->applyActorScopeToSessions($query)
            ->where('user_id', Auth::id());
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, float>
     */
    private function userChargeOutRateMapForIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'hourly_rate'])
            ->mapWithKeys(fn (User $user): array => [(int) $user->id => (float) ($user->hourly_rate ?? 0.0)])
            ->all();
    }

    /**
     * @param array<int, float> $userRateMap
     */
    private function resolveSessionHourlyRate(?int $sessionUserId, float $clientHourlyRate, array $userRateMap): float
    {
        if ($sessionUserId !== null) {
            $userRate = (float) ($userRateMap[$sessionUserId] ?? 0.0);

            if ($userRate > 0) {
                return $userRate;
            }
        }

        return $clientHourlyRate;
    }

    /**
     * @param array<int, float> $clientRatesByInvoice
     * @return array<int, float>
     */
    private function calculateBillableTimeByInvoiceFromSessionRows($sessionRows, array $clientRatesByInvoice): array
    {
        $userIds = collect($sessionRows)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($userId): int => (int) $userId)
            ->unique()
            ->values()
            ->all();

        $userRateMap = $this->userChargeOutRateMapForIds($userIds);
        $billableByInvoice = [];

        foreach ($sessionRows as $row) {
            $invoiceId = (int) ($row->invoice_id ?? 0);

            if ($invoiceId <= 0) {
                continue;
            }

            $clientRate = (float) ($clientRatesByInvoice[$invoiceId] ?? 0.0);
            $sessionUserId = $row->user_id !== null ? (int) $row->user_id : null;
            $hourlyRate = $this->resolveSessionHourlyRate($sessionUserId, $clientRate, $userRateMap);
            $durationSeconds = max(0, (int) ($row->total_duration_seconds ?? 0));

            $billableByInvoice[$invoiceId] = (float) ($billableByInvoice[$invoiceId] ?? 0.0)
                + (($durationSeconds / 3600) * $hourlyRate);
        }

        foreach ($billableByInvoice as $invoiceId => $amount) {
            $billableByInvoice[$invoiceId] = round((float) $amount, 2);
        }

        return $billableByInvoice;
    }

    private function findInvoiceForActorOrFail(int $invoiceId): Invoice
    {
        $invoice = $this->applyActorScope(Invoice::query())
            ->with(['client', 'financialYear'])
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
            ->where('team_id', $this->currentTeamIdOrFail())
            ->whereKey($clientId)
            ->first();

        abort_unless($client !== null, 403, 'Client does not belong to this user.');

        return $client;
    }

    private function findAnyActiveSessionForActor(): ?TimerSession
    {
        return $this->applyCurrentUserScopeToSessions(TimerSession::query())
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();
    }

    private function findFinancialYearForActorOrFail(int $financialYearId): FinancialYear
    {
        $financialYear = FinancialYear::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->whereKey($financialYearId)
            ->first();

        abort_unless($financialYear !== null, 403, 'Financial year does not belong to this user.');

        return $financialYear;
    }

    private function financialYearsForActor()
    {
        $userId = (int) Auth::id();
        $teamId = $this->currentTeamIdOrFail();
        $this->findOrCreateFinancialYearForTeam($userId, $teamId, $this->defaultNzFinancialYearStart());

        return FinancialYear::query()
            ->where('team_id', $teamId)
            ->orderByDesc('start_year')
            ->get();
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

    private function assignedSessionsForInvoice(Invoice $invoice)
    {
        return $this->applyActorScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->with(['task.project', 'user:id,name'])
            ->orderByDesc('started_at')
            ->get();
    }

    private function clientTasksForInvoice(Invoice $invoice)
    {
        if (!$invoice->client_id) {
            return collect();
        }

        return Task::query()
            ->where('client_id', $invoice->client_id)
            ->whereHas('project', function (Builder $query): void {
                $query->where('team_id', $this->currentTeamIdOrFail());
            })
            ->with(['project'])
            ->orderBy('name')
            ->get();
    }

    private function resolveTaskIdForInvoiceClient(Invoice $invoice, ?int $projectId, ?int $taskId): ?int
    {
        if (!$invoice->client_id) {
            return null;
        }

        if ($taskId !== null) {
            $task = Task::query()
                ->where('team_id', $this->currentTeamIdOrFail())
                ->where('client_id', $invoice->client_id)
                ->whereKey($taskId)
                ->where('is_active', true)
                ->whereHas('project', function (Builder $query): void {
                    $query->where('team_id', $this->currentTeamIdOrFail());
                })
                ->first();

            if (!$task) {
                return null;
            }

            return (int) $task->id;
        }

        if ($projectId === null) {
            return null;
        }

        $fallbackTask = Task::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->where('client_id', $invoice->client_id)
            ->where('project_id', $projectId)
            ->where('is_active', true)
            ->whereHas('project', function (Builder $query): void {
                $query->where('team_id', $this->currentTeamIdOrFail());
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        return $fallbackTask ? (int) $fallbackTask->id : null;
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

    private function abortUnlessCurrentTeamOwner(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->currentTeam && $user->ownsTeam($user->currentTeam), 403, 'Only the team owner can access tax summary pages.');
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
        $invoice->loadMissing('client');

        $totals = $this->applyActorScopeToSessions(TimerSession::query())
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('stopped_at')
            ->selectRaw('invoice_id, user_id, COUNT(*) as sessions_count, COALESCE(SUM(duration_seconds), 0) as total_duration_seconds')
            ->groupBy('invoice_id', 'user_id')
            ->get();

        $sessionsCount = (int) $totals->sum('sessions_count');
        $totalDurationSeconds = (int) $totals->sum('total_duration_seconds');
        $totalExpensesAmount = (float) (Expense::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount'));
        $billableByInvoice = $this->calculateBillableTimeByInvoiceFromSessionRows($totals, [
            (int) $invoice->id => $invoice->client ? (float) $invoice->client->hourly_rate : 0.0,
        ]);
        $billableTimeAmount = (float) ($billableByInvoice[(int) $invoice->id] ?? 0.0);
        $subtotalAmount = round($billableTimeAmount + $totalExpensesAmount, 2);
        $discountAmount = $this->calculateInvoiceDiscountAmount(
            $subtotalAmount,
            $invoice->discount_type,
            (float) ($invoice->discount_value ?? 0)
        );
        $totalBillableAmount = round(max(0, $subtotalAmount - $discountAmount), 2);

        return [
            'sessions_count' => $sessionsCount,
            'total_duration_seconds' => $totalDurationSeconds,
            'total_expenses_amount' => $totalExpensesAmount,
            'billable_time_amount' => $billableTimeAmount,
            'subtotal_amount' => $subtotalAmount,
            'discount_type' => $invoice->discount_type,
            'discount_value' => (float) ($invoice->discount_value ?? 0),
            'discount_amount' => $discountAmount,
            'total_billable_amount' => $totalBillableAmount,
        ];
    }

    private function calculateTaxSummary(array $summary, ?string $baseCurrency = null, bool $includeAllocations = true): array
    {
        $user = Auth::user();
        $grossAmount = (float) ($summary['total_billable_amount'] ?? 0);
        $deductibleBusinessExpensesAmount = round((float) ($summary['deductible_business_expenses_amount'] ?? 0), 2);
        $taxableAmount = round(max(0, $grossAmount - $deductibleBusinessExpensesAmount), 2);
        $resolvedBaseCurrency = $this->normalizeCurrencyCode($baseCurrency) ?? 'USD';
        $additionalTaxItems = $this->teamAdditionalTaxItems();
        $rateCache = [];
        $additionalTaxesBeforeDeductions = $this->calculateAdditionalTaxItems($grossAmount, $additionalTaxItems, $resolvedBaseCurrency, $rateCache);
        $totalTaxBeforeDeductionsAmount = round($additionalTaxesBeforeDeductions['tax_total'], 2);
        $additionalTaxes = $this->calculateAdditionalTaxItems($taxableAmount, $additionalTaxItems, $resolvedBaseCurrency, $rateCache);
        $totalTaxAmount = round($additionalTaxes['tax_total'], 2);
        $taxSavingsFromDeductionsAmount = round(max(0, $totalTaxBeforeDeductionsAmount - $totalTaxAmount), 2);
        $allocationTotal = $includeAllocations ? $additionalTaxes['allocation_total'] : 0.0;
        $netAfterTaxAmount = round($grossAmount - $totalTaxAmount, 2);
        $totalDeductionsAmount = round($totalTaxAmount + $allocationTotal, 2);
        $netAmount = round($netAfterTaxAmount - $allocationTotal, 2);

        return [
            'gross_amount' => $grossAmount,
            'deductible_business_expenses_amount' => $deductibleBusinessExpensesAmount,
            'taxable_amount' => $taxableAmount,
            'additional_tax_items' => $additionalTaxes['items'],
            'currency' => $resolvedBaseCurrency,
            'additional_tax_total' => $additionalTaxes['tax_total'],
            'total_tax_before_deductible_expenses_amount' => $totalTaxBeforeDeductionsAmount,
            'total_tax_after_deductible_expenses_amount' => $totalTaxAmount,
            'tax_savings_from_deductible_expenses_amount' => $taxSavingsFromDeductionsAmount,
            'allocation_total' => $allocationTotal,
            'total_tax_amount' => $totalTaxAmount,
            'net_after_tax_amount' => $netAfterTaxAmount,
            'total_deductions_amount' => $totalDeductionsAmount,
            'net_amount' => $netAmount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teamAdditionalTaxItems(): array
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->currentTeam) {
            return [];
        }

        return UserAdditionalTax::query()
            ->where('team_id', (int) $user->currentTeam->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'name', 'category', 'value_type', 'value', 'currency', 'position'])
            ->map(fn ($item): array => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'category' => (string) $item->category,
                'value_type' => (string) $item->value_type,
                'value' => (float) $item->value,
                'currency' => $item->currency ? (string) $item->currency : null,
                'position' => (int) $item->position,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, float|null> $rateCache
     * @return array{items: array<int, array<string, mixed>>, tax_total: float, allocation_total: float}
     */
    private function calculateAdditionalTaxItems(float $grossAmount, array $items, string $baseCurrency, array &$rateCache = []): array
    {
        $normalizedItems = collect($items)->map(function (array $item) use ($grossAmount, $baseCurrency, &$rateCache): array {
            $valueType = strtolower((string) ($item['value_type'] ?? 'percentage'));
            $category = strtolower((string) ($item['category'] ?? 'tax'));
            $value = (float) ($item['value'] ?? 0);
            $isPercentage = $valueType === 'percentage';
            $itemCurrency = $this->normalizeCurrencyCode($item['currency'] ?? null) ?? $baseCurrency;

            if ($isPercentage) {
                $amount = round($grossAmount * ($value / 100), 2);
                $effectiveRate = 1.0;
                $effectiveCurrency = $baseCurrency;
            } else {
                $effectiveRate = $this->resolveCurrencyRate($itemCurrency, $baseCurrency, $rateCache);
                $amount = round($value * $effectiveRate, 2);
                $effectiveCurrency = $itemCurrency;
            }

            return [
                'id' => (int) ($item['id'] ?? 0),
                'name' => (string) ($item['name'] ?? 'Additional Charge'),
                'category' => in_array($category, ['tax', 'levy', 'allocation'], true) ? $category : 'tax',
                'value_type' => $isPercentage ? 'percentage' : 'fixed',
                'value' => round($value, 2),
                'currency' => $isPercentage ? null : $effectiveCurrency,
                'amount' => $amount,
                'amount_currency' => $baseCurrency,
                'conversion_rate' => $effectiveRate,
                'position' => (int) ($item['position'] ?? 0),
            ];
        })->values();

        $allocationTotal = round((float) $normalizedItems
            ->where('category', 'allocation')
            ->sum('amount'), 2);

        $taxTotal = round((float) $normalizedItems
            ->where('category', '!=', 'allocation')
            ->sum('amount'), 2);

        return [
            'items' => $normalizedItems->all(),
            'tax_total' => $taxTotal,
            'allocation_total' => $allocationTotal,
        ];
    }

    /**
     * @param array<string, float|null> $rateCache
     */
    private function resolveCurrencyRate(string $sourceCurrency, string $targetCurrency, array &$rateCache): float
    {
        if ($sourceCurrency === $targetCurrency) {
            return 1.0;
        }

        $cacheKey = $sourceCurrency . ':' . $targetCurrency;

        if (array_key_exists($cacheKey, $rateCache)) {
            return $rateCache[$cacheKey] !== null ? (float) $rateCache[$cacheKey] : 1.0;
        }

        $liveRate = $this->fetchWiseLiveRate($sourceCurrency, $targetCurrency);
        $resolvedRate = $liveRate ? (float) $liveRate['rate'] : 1.0;
        $rateCache[$cacheKey] = $resolvedRate;

        return $resolvedRate;
    }

    private function buildWiseCurrencyConversion(Invoice $invoice, array $taxSummary): array
    {
        $actor = Auth::user();
        $isFinalized = in_array($invoice->status, ['finalized', 'paid'], true);
        $source = $this->normalizeCurrencyCode($invoice->conversion_source_currency)
            ?? $this->normalizeCurrencyCode($invoice->client ? (string) $invoice->client->currency : null)
            ?? 'USD';
        $target = $this->currencyForCountry($actor ? (string) $actor->country : null);
        $storedTarget = $this->normalizeCurrencyCode($invoice->conversion_target_currency);
        $grossAmount = (float) ($taxSummary['gross_amount'] ?? 0);
        $totalTaxAmount = (float) ($taxSummary['total_tax_amount'] ?? 0);
        $allocationTotal = (float) ($taxSummary['allocation_total'] ?? 0);
        $netAfterTaxAmount = (float) ($taxSummary['net_after_tax_amount'] ?? ($grossAmount - $totalTaxAmount));
        $totalDeductionsAmount = (float) ($taxSummary['total_deductions_amount'] ?? ($totalTaxAmount + $allocationTotal));
        $netAmount = (float) ($taxSummary['net_amount'] ?? 0);

        $storedRate = (float) ($invoice->conversion_rate ?? 0);
        $storedAsOf = $invoice->conversion_rate_fetched_at
            ? $invoice->conversion_rate_fetched_at->copy()->toIso8601String()
            : null;

        if ($source === $target) {
            return [
                'available' => true,
                'is_same_currency' => true,
                'is_locked' => $isFinalized,
                'source_currency' => $source,
                'target_currency' => $target,
                'rate' => 1.0,
                'as_of' => now()->toIso8601String(),
                'gross_amount_converted' => round($grossAmount, 2),
                'total_tax_amount_converted' => round($totalTaxAmount, 2),
                'net_after_tax_amount_converted' => round($netAfterTaxAmount, 2),
                'allocation_total_converted' => round($allocationTotal, 2),
                'total_deductions_amount_converted' => round($totalDeductionsAmount, 2),
                'net_amount_converted' => round($netAmount, 2),
                'message' => 'Client currency already matches your country currency.',
            ];
        }

        if ($storedRate > 0 && $storedTarget === $target) {
            return [
                'available' => true,
                'is_same_currency' => false,
                'is_locked' => $isFinalized,
                'source_currency' => $source,
                'target_currency' => $target,
                'rate' => $storedRate,
                'as_of' => $storedAsOf ?? now()->toIso8601String(),
                'gross_amount_converted' => round($grossAmount * $storedRate, 2),
                'total_tax_amount_converted' => round($totalTaxAmount * $storedRate, 2),
                'net_after_tax_amount_converted' => round($netAfterTaxAmount * $storedRate, 2),
                'allocation_total_converted' => round($allocationTotal * $storedRate, 2),
                'total_deductions_amount_converted' => round($totalDeductionsAmount * $storedRate, 2),
                'net_amount_converted' => round($netAmount * $storedRate, 2),
                'message' => $isFinalized
                    ? 'Rate locked at invoice finalization.'
                    : 'Using stored conversion snapshot for this invoice.',
            ];
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://wise.com/rates/live', [
                    'source' => $source,
                    'target' => $target,
                    'length' => 1,
                ]);
        } catch (Throwable $exception) {
            return [
                'available' => false,
                'is_same_currency' => false,
                'is_locked' => false,
                'source_currency' => $source,
                'target_currency' => $target,
                'message' => 'Live conversion is temporarily unavailable.',
            ];
        }

        if (!$response->ok()) {
            return [
                'available' => false,
                'is_same_currency' => false,
                'is_locked' => false,
                'source_currency' => $source,
                'target_currency' => $target,
                'message' => 'Live conversion is temporarily unavailable.',
            ];
        }

        $payload = $response->json();
        $rate = is_array($payload) ? (float) ($payload['value'] ?? 0) : 0.0;

        if ($rate <= 0) {
            return [
                'available' => false,
                'is_same_currency' => false,
                'is_locked' => $isFinalized,
                'source_currency' => $source,
                'target_currency' => $target,
                'message' => 'Live conversion is temporarily unavailable.',
            ];
        }

        $timestampMs = is_array($payload) && isset($payload['time']) ? (int) $payload['time'] : null;
        $asOf = $timestampMs
            ? CarbonImmutable::createFromTimestampMs($timestampMs, 'UTC')->toIso8601String()
            : now()->toIso8601String();

        return [
            'available' => true,
            'is_same_currency' => false,
            'is_locked' => false,
            'source_currency' => $source,
            'target_currency' => $target,
            'rate' => $rate,
            'as_of' => $asOf,
            'gross_amount_converted' => round($grossAmount * $rate, 2),
            'total_tax_amount_converted' => round($totalTaxAmount * $rate, 2),
            'net_after_tax_amount_converted' => round($netAfterTaxAmount * $rate, 2),
            'allocation_total_converted' => round($allocationTotal * $rate, 2),
            'total_deductions_amount_converted' => round($totalDeductionsAmount * $rate, 2),
            'net_amount_converted' => round($netAmount * $rate, 2),
            'message' => $isFinalized && $storedRate > 0 && $storedTarget !== null && $storedTarget !== $target
                ? 'Stored finalized rate target differs from your current country currency; showing live conversion.'
                : null,
        ];
    }

    private function storeFinalizedWiseConversionRate(Invoice $invoice): void
    {
        $actor = Auth::user();
        $source = $this->normalizeCurrencyCode($invoice->client ? (string) $invoice->client->currency : null) ?? 'USD';
        $target = $this->currencyForCountry($actor ? (string) $actor->country : null);

        $invoice->conversion_source_currency = $source;
        $invoice->conversion_target_currency = $target;

        if ($source === $target) {
            $invoice->conversion_rate = 1.0;
            $invoice->conversion_rate_fetched_at = now();
            return;
        }

        $liveRate = $this->fetchWiseLiveRate($source, $target);

        if ($liveRate === null) {
            return;
        }

        $invoice->conversion_rate = (float) $liveRate['rate'];
        $invoice->conversion_rate_fetched_at = $liveRate['as_of'];
    }

    private function fetchWiseLiveRate(string $source, string $target): ?array
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://wise.com/rates/live', [
                    'source' => $source,
                    'target' => $target,
                    'length' => 1,
                ]);
        } catch (Throwable $exception) {
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $payload = $response->json();
        $rate = is_array($payload) ? (float) ($payload['value'] ?? 0) : 0.0;

        if ($rate <= 0) {
            return null;
        }

        $timestampMs = is_array($payload) && isset($payload['time']) ? (int) $payload['time'] : null;
        $asOf = $timestampMs
            ? CarbonImmutable::createFromTimestampMs($timestampMs, 'UTC')
            : now();

        return [
            'rate' => $rate,
            'as_of' => $asOf,
        ];
    }

    private function normalizeCurrencyCode(?string $currencyCode): ?string
    {
        $value = strtoupper(trim((string) $currencyCode));

        if (!preg_match('/^[A-Z]{3}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function currencyForCountry(?string $countryCode): string
    {
        $value = strtoupper(trim((string) $countryCode));

        $currencyByCountry = [
            'NZ' => 'NZD',
            'AU' => 'AUD',
            'US' => 'USD',
            'CA' => 'CAD',
            'GB' => 'GBP',
            'JP' => 'JPY',
            'SG' => 'SGD',
            'IN' => 'INR',
            'CH' => 'CHF',
            'SE' => 'SEK',
            'NO' => 'NOK',
            'DK' => 'DKK',
            'HK' => 'HKD',
            'ZA' => 'ZAR',
            'MX' => 'MXN',
            'BR' => 'BRL',
            'CN' => 'CNY',
            'KR' => 'KRW',
            'AE' => 'AED',
            'IE' => 'EUR',
            'FR' => 'EUR',
            'DE' => 'EUR',
            'ES' => 'EUR',
            'IT' => 'EUR',
            'NL' => 'EUR',
            'PT' => 'EUR',
            'BE' => 'EUR',
            'AT' => 'EUR',
            'FI' => 'EUR',
            'GR' => 'EUR',
            'LU' => 'EUR',
        ];

        return $currencyByCountry[$value] ?? 'USD';
    }

    private function defaultNzFinancialYearStart(): int
    {
        $nowNz = CarbonImmutable::now('Pacific/Auckland');

        return $nowNz->month >= 4 ? $nowNz->year : $nowNz->subYear()->year;
    }

    private function nzFinancialYearPeriod(int $financialYearStart): array
    {
        $start = CarbonImmutable::create($financialYearStart, 4, 1, 0, 0, 0, 'Pacific/Auckland');
        $end = $start->addYear()->subDay();

        return [
            'start' => $start,
            'end' => $end,
            'label' => $financialYearStart . '/' . ($financialYearStart + 1),
        ];
    }

    private function financialYearInvoiceSummary(FinancialYear $financialYear): array
    {
        $businessExpenses = BusinessExpense::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->where('financial_year_id', $financialYear->id)
            ->get(['amount', 'tax_deductible', 'deductible_percentage']);

        $totalBusinessExpensesAmount = round((float) $businessExpenses->sum(fn (BusinessExpense $expense): float => (float) $expense->amount), 2);
        $deductibleBusinessExpensesAmount = round((float) $businessExpenses->sum(function (BusinessExpense $expense): float {
            if (! $expense->tax_deductible) {
                return 0.0;
            }

            $deductiblePercentage = (float) ($expense->deductible_percentage ?? 100);
            $normalizedPercentage = max(0, min(100, $deductiblePercentage));

            return (float) $expense->amount * ($normalizedPercentage / 100);
        }), 2);

        $invoices = $this->applyActorScope(Invoice::query())
            ->where('financial_year_id', $financialYear->id)
            ->where('status', 'paid')
            ->with('client:id,hourly_rate')
            ->get(['id', 'client_id', 'discount_type', 'discount_value']);

        $invoiceIds = $invoices->pluck('id')->all();

        if (empty($invoiceIds)) {
            return [
                'invoice_count' => 0,
                'sessions_count' => 0,
                'total_duration_seconds' => 0,
                'total_expenses_amount' => 0,
                'billable_time_amount' => 0,
                'subtotal_amount' => 0,
                'total_discount_amount' => 0,
                'total_billable_amount' => 0,
                'total_business_expenses_amount' => $totalBusinessExpensesAmount,
                'deductible_business_expenses_amount' => $deductibleBusinessExpensesAmount,
            ];
        }

        $sessionTotals = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNotNull('stopped_at')
            ->selectRaw('invoice_id, user_id, COUNT(*) as sessions_count, COALESCE(SUM(duration_seconds), 0) as total_duration_seconds')
            ->groupBy('invoice_id', 'user_id')
            ->get();

        $sessionsCount = (int) $sessionTotals->sum('sessions_count');
        $totalDurationSeconds = (int) $sessionTotals->sum('total_duration_seconds');
        $expenseTotalsByInvoice = Expense::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->selectRaw('invoice_id, COALESCE(SUM(amount), 0) as total_expenses_amount')
            ->groupBy('invoice_id')
            ->get()
            ->keyBy('invoice_id');

        $clientRatesByInvoice = $invoices->mapWithKeys(fn (Invoice $invoice): array => [
            (int) $invoice->id => $invoice->client ? (float) $invoice->client->hourly_rate : 0.0,
        ])->all();

        $billableTimeByInvoice = collect($this->calculateBillableTimeByInvoiceFromSessionRows($sessionTotals, $clientRatesByInvoice));

        $billableTimeAmount = round((float) $billableTimeByInvoice->sum(), 2);
        $totalExpensesAmount = round((float) $expenseTotalsByInvoice->sum('total_expenses_amount'), 2);
        $subtotalAmount = 0.0;
        $totalDiscountAmount = 0.0;

        foreach ($invoices as $invoice) {
            $invoiceBillableTime = (float) ($billableTimeByInvoice[$invoice->id] ?? 0);
            $invoiceExpenses = (float) ($expenseTotalsByInvoice[$invoice->id]->total_expenses_amount ?? 0);
            $invoiceSubtotal = round($invoiceBillableTime + $invoiceExpenses, 2);
            $invoiceDiscount = $this->calculateInvoiceDiscountAmount(
                $invoiceSubtotal,
                $invoice->discount_type,
                (float) ($invoice->discount_value ?? 0)
            );

            $subtotalAmount += $invoiceSubtotal;
            $totalDiscountAmount += $invoiceDiscount;
        }

        $subtotalAmount = round($subtotalAmount, 2);
        $totalDiscountAmount = round($totalDiscountAmount, 2);
        $totalBillableAmount = round(max(0, $subtotalAmount - $totalDiscountAmount), 2);

        return [
            'invoice_count' => count($invoiceIds),
            'sessions_count' => $sessionsCount,
            'total_duration_seconds' => $totalDurationSeconds,
            'total_expenses_amount' => $totalExpensesAmount,
            'billable_time_amount' => $billableTimeAmount,
            'subtotal_amount' => $subtotalAmount,
            'total_discount_amount' => $totalDiscountAmount,
            'total_billable_amount' => $totalBillableAmount,
            'total_business_expenses_amount' => $totalBusinessExpensesAmount,
            'deductible_business_expenses_amount' => $deductibleBusinessExpensesAmount,
        ];
    }

    private function financialYearConvertedTaxSummary(FinancialYear $financialYear, array $financialSummary): array
    {
        $actor = Auth::user();
        $targetCurrency = $this->currencyForCountry($actor ? (string) $actor->country : null);
        $additionalTaxItems = $this->teamAdditionalTaxItems();

        $allInvoices = $this->applyActorScope(Invoice::query())
            ->where('financial_year_id', $financialYear->id)
            ->with('client:id,hourly_rate,currency')
            ->get([
                'id',
                'status',
                'due_at',
                'client_id',
                'discount_type',
                'discount_value',
                'conversion_source_currency',
                'conversion_target_currency',
                'conversion_rate',
            ]);

        $paidInvoices = $allInvoices->where('status', 'paid')->values();
        $unpaidInvoices = $allInvoices->where('status', 'finalized')->values();
        $uninvoicedInvoices = $allInvoices->filter(fn (Invoice $invoice): bool => !in_array($invoice->status, ['finalized', 'paid'], true))->values();
        $overdueInvoices = $allInvoices->filter(function (Invoice $invoice): bool {
            if ($invoice->status === 'paid' || $invoice->due_at === null) {
                return false;
            }

            return $invoice->due_at->lt(now());
        })->values();

        $rateCache = [];
        $paidInvoiceRates = $this->buildConvertedInvoiceRateMap($paidInvoices, $targetCurrency, $rateCache);
        $unpaidInvoiceRates = $this->buildConvertedInvoiceRateMap($unpaidInvoices, $targetCurrency, $rateCache);
        $uninvoicedInvoiceRates = $this->buildConvertedInvoiceRateMap($uninvoicedInvoices, $targetCurrency, $rateCache);
        $overdueInvoiceRates = $this->buildConvertedInvoiceRateMap($overdueInvoices, $targetCurrency, $rateCache);

        $paidProjectTotalsConverted = $this->buildFinancialYearProjectTotalsConverted(
            array_keys($paidInvoiceRates),
            $paidInvoiceRates
        );
        $unpaidProjectTotalsConverted = $this->buildFinancialYearProjectTotalsConverted(
            array_keys($unpaidInvoiceRates),
            $unpaidInvoiceRates
        );
        $uninvoicedProjectTotalsConverted = $this->buildFinancialYearProjectTotalsConverted(
            array_keys($uninvoicedInvoiceRates),
            $uninvoicedInvoiceRates
        );
        $overdueProjectTotalsConverted = $this->buildFinancialYearProjectTotalsConverted(
            array_keys($overdueInvoiceRates),
            $overdueInvoiceRates
        );

        if ($paidInvoices->isEmpty()) {
            return [
                'target_currency' => $targetCurrency,
                'total_invoices' => 0,
                'converted_invoices' => 0,
                'missing_rate_invoices' => 0,
                'paid_project_total_invoice_count' => count($paidInvoiceRates),
                'unpaid_project_total_invoice_count' => count($unpaidInvoiceRates),
                'uninvoiced_project_total_invoice_count' => count($uninvoicedInvoiceRates),
                'overdue_project_total_invoice_count' => count($overdueInvoiceRates),
                'billable_time_amount_converted' => 0.0,
                'total_expenses_amount_converted' => 0.0,
                'subtotal_amount_converted' => 0.0,
                'total_discount_amount_converted' => 0.0,
                'gross_amount_converted' => 0.0,
                'total_business_expenses_amount_converted' => round((float) ($financialSummary['total_business_expenses_amount'] ?? 0), 2),
                'deductible_business_expenses_amount_converted' => round((float) ($financialSummary['deductible_business_expenses_amount'] ?? 0), 2),
                'taxable_amount_converted' => 0.0,
                'additional_tax_items' => [],
                'total_tax_before_deductible_expenses_amount_converted' => 0.0,
                'total_tax_after_deductible_expenses_amount_converted' => 0.0,
                'tax_savings_from_deductible_expenses_amount_converted' => 0.0,
                'total_tax_amount_converted' => 0.0,
                'net_after_tax_amount_converted' => 0.0,
                'allocation_total_converted' => 0.0,
                'total_deductions_amount_converted' => 0.0,
                'net_amount_converted' => 0.0,
                'project_totals_converted' => $paidProjectTotalsConverted,
                'unpaid_project_totals_converted' => $unpaidProjectTotalsConverted,
                'uninvoiced_project_totals_converted' => $uninvoicedProjectTotalsConverted,
                'overdue_project_totals_converted' => $overdueProjectTotalsConverted,
            ];
        }

        $invoiceIds = $paidInvoices->pluck('id')->all();

        $sessionTotals = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNotNull('stopped_at')
            ->selectRaw('invoice_id, user_id, COALESCE(SUM(duration_seconds), 0) as total_duration_seconds')
            ->groupBy('invoice_id', 'user_id')
            ->get();

        $expenseTotals = Expense::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->selectRaw('invoice_id, COALESCE(SUM(amount), 0) as total_expenses_amount')
            ->groupBy('invoice_id')
            ->get()
            ->keyBy('invoice_id');

        $clientRatesByInvoice = $paidInvoices->mapWithKeys(fn (Invoice $invoice): array => [
            (int) $invoice->id => $invoice->client ? (float) $invoice->client->hourly_rate : 0.0,
        ])->all();
        $billableTimeByInvoice = $this->calculateBillableTimeByInvoiceFromSessionRows($sessionTotals, $clientRatesByInvoice);

        $convertedInvoices = 0;
        $missingRateInvoices = 0;
        $billableTimeAmountConverted = 0.0;
        $totalExpensesAmountConverted = 0.0;
        $subtotalAmountConverted = 0.0;
        $totalDiscountAmountConverted = 0.0;
        $grossAmountConverted = 0.0;
        $allocationTotalConverted = 0.0;
        $additionalTaxItemsConverted = [];

        foreach ($paidInvoices as $invoice) {
            $sourceCurrency = $this->normalizeCurrencyCode($invoice->conversion_source_currency)
                ?? $this->normalizeCurrencyCode($invoice->client ? (string) $invoice->client->currency : null);
            $conversionRate = $paidInvoiceRates[$invoice->id]['conversion_rate'] ?? null;

            if ($sourceCurrency === null || $conversionRate === null || $conversionRate <= 0) {
                $missingRateInvoices++;
                continue;
            }

            $billableTimeAmount = (float) ($billableTimeByInvoice[(int) $invoice->id] ?? 0.0);
            $expensesAmount = (float) (($expenseTotals[$invoice->id]->total_expenses_amount ?? 0));
            $subtotalAmount = round($billableTimeAmount + $expensesAmount, 2);
            $discountAmount = $this->calculateInvoiceDiscountAmount(
                $subtotalAmount,
                $invoice->discount_type,
                (float) ($invoice->discount_value ?? 0)
            );
            $grossAmount = round(max(0, $subtotalAmount - $discountAmount), 2);
            $additionalTaxes = $this->calculateAdditionalTaxItems($grossAmount, $additionalTaxItems, $sourceCurrency, $rateCache);
            $totalTaxAmount = round($additionalTaxes['tax_total'], 2);
            $allocationTotal = 0.0;

            foreach ($additionalTaxes['items'] as $item) {
                $itemKey = (string) ($item['id'] ?? '') . ':' . strtolower((string) ($item['name'] ?? 'additional-charge'));

                if (!array_key_exists($itemKey, $additionalTaxItemsConverted)) {
                    $additionalTaxItemsConverted[$itemKey] = [
                        'id' => (int) ($item['id'] ?? 0),
                        'name' => (string) ($item['name'] ?? 'Additional Charge'),
                        'category' => (string) ($item['category'] ?? 'tax'),
                        'value_type' => (string) ($item['value_type'] ?? 'percentage'),
                        'value' => round((float) ($item['value'] ?? 0), 2),
                        'currency' => $item['currency'] ? (string) $item['currency'] : null,
                        'position' => (int) ($item['position'] ?? 0),
                        'amount' => 0.0,
                        'amount_currency' => $targetCurrency,
                    ];
                }

                $additionalTaxItemsConverted[$itemKey]['amount'] += round((float) ($item['amount'] ?? 0) * $conversionRate, 2);
            }

            $billableTimeAmountConverted += round($billableTimeAmount * $conversionRate, 2);
            $totalExpensesAmountConverted += round($expensesAmount * $conversionRate, 2);
            $subtotalAmountConverted += round($subtotalAmount * $conversionRate, 2);
            $totalDiscountAmountConverted += round($discountAmount * $conversionRate, 2);
            $grossAmountConverted += round($grossAmount * $conversionRate, 2);
            $allocationTotalConverted += round($allocationTotal * $conversionRate, 2);
            $convertedInvoices += 1;
        }

        $totalBusinessExpensesAmountConverted = round((float) ($financialSummary['total_business_expenses_amount'] ?? 0), 2);
        $deductibleBusinessExpensesAmountConverted = round((float) ($financialSummary['deductible_business_expenses_amount'] ?? 0), 2);
        $taxableAmountConverted = round(max(0, $grossAmountConverted - $deductibleBusinessExpensesAmountConverted), 2);
        $taxRateCache = [];
        $taxesBeforeDeductions = $this->calculateAdditionalTaxItems($grossAmountConverted, $additionalTaxItems, $targetCurrency, $taxRateCache);
        $taxesAfterDeductions = $this->calculateAdditionalTaxItems($taxableAmountConverted, $additionalTaxItems, $targetCurrency, $taxRateCache);
        $totalTaxBeforeDeductionsAmountConverted = round((float) ($taxesBeforeDeductions['tax_total'] ?? 0), 2);
        $totalTaxAfterDeductionsAmountConverted = round((float) ($taxesAfterDeductions['tax_total'] ?? 0), 2);
        $taxSavingsFromDeductionsAmountConverted = round(max(0, $totalTaxBeforeDeductionsAmountConverted - (float) ($taxesAfterDeductions['tax_total'] ?? 0)), 2);
        $netAfterTaxAmountConverted = round($grossAmountConverted - $totalTaxAfterDeductionsAmountConverted, 2);
        $totalDeductionsAmountConverted = round($totalTaxAfterDeductionsAmountConverted + $allocationTotalConverted, 2);
        $netAmountConverted = round($netAfterTaxAmountConverted - $allocationTotalConverted, 2);

        $normalizedAdditionalTaxItems = collect($additionalTaxItemsConverted)
            ->map(function (array $item): array {
                $item['amount'] = round((float) $item['amount'], 2);
                return $item;
            })
            ->sortBy([
                ['position', 'asc'],
                ['id', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'target_currency' => $targetCurrency,
            'total_invoices' => $paidInvoices->count(),
            'converted_invoices' => $convertedInvoices,
            'missing_rate_invoices' => $missingRateInvoices,
            'paid_project_total_invoice_count' => count($paidInvoiceRates),
            'unpaid_project_total_invoice_count' => count($unpaidInvoiceRates),
            'uninvoiced_project_total_invoice_count' => count($uninvoicedInvoiceRates),
            'overdue_project_total_invoice_count' => count($overdueInvoiceRates),
            'billable_time_amount_converted' => round($billableTimeAmountConverted, 2),
            'total_expenses_amount_converted' => round($totalExpensesAmountConverted, 2),
            'subtotal_amount_converted' => round($subtotalAmountConverted, 2),
            'total_discount_amount_converted' => round($totalDiscountAmountConverted, 2),
            'gross_amount_converted' => round($grossAmountConverted, 2),
            'total_business_expenses_amount_converted' => $totalBusinessExpensesAmountConverted,
            'deductible_business_expenses_amount_converted' => $deductibleBusinessExpensesAmountConverted,
            'taxable_amount_converted' => $taxableAmountConverted,
            'additional_tax_items' => $normalizedAdditionalTaxItems,
            'total_tax_before_deductible_expenses_amount_converted' => $totalTaxBeforeDeductionsAmountConverted,
            'total_tax_after_deductible_expenses_amount_converted' => $totalTaxAfterDeductionsAmountConverted,
            'tax_savings_from_deductible_expenses_amount_converted' => $taxSavingsFromDeductionsAmountConverted,
            'total_tax_amount_converted' => $totalTaxAfterDeductionsAmountConverted,
            'net_after_tax_amount_converted' => round($netAfterTaxAmountConverted, 2),
            'allocation_total_converted' => round($allocationTotalConverted, 2),
            'total_deductions_amount_converted' => round($totalDeductionsAmountConverted, 2),
            'net_amount_converted' => round($netAmountConverted, 2),
            'project_totals_converted' => $paidProjectTotalsConverted,
            'unpaid_project_totals_converted' => $unpaidProjectTotalsConverted,
            'uninvoiced_project_totals_converted' => $uninvoicedProjectTotalsConverted,
            'overdue_project_totals_converted' => $overdueProjectTotalsConverted,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, Invoice> $invoices
     * @param array<string, float|null> $rateCache
    * @return array<int, array{client_hourly_rate: float, conversion_rate: float}>
     */
    private function buildConvertedInvoiceRateMap($invoices, string $targetCurrency, array &$rateCache): array
    {
        $rates = [];

        foreach ($invoices as $invoice) {
            $sourceCurrency = $this->normalizeCurrencyCode($invoice->conversion_source_currency)
                ?? $this->normalizeCurrencyCode($invoice->client ? (string) $invoice->client->currency : null);
            $conversionRate = $this->invoiceConversionRateToTarget($invoice, $sourceCurrency, $targetCurrency, $rateCache);

            if ($sourceCurrency === null || $conversionRate === null || $conversionRate <= 0) {
                continue;
            }

            $rates[(int) $invoice->id] = [
                'client_hourly_rate' => $invoice->client ? (float) $invoice->client->hourly_rate : 0.0,
                'conversion_rate' => (float) $conversionRate,
            ];
        }

        return $rates;
    }

    /**
     * @param array<int, int> $invoiceIds
    * @param array<int, array{client_hourly_rate: float, conversion_rate: float}> $convertedInvoiceRates
     * @return array<int, array<string, mixed>>
     */
    private function buildFinancialYearProjectTotalsConverted(array $invoiceIds, array $convertedInvoiceRates): array
    {
        if (empty($invoiceIds)) {
            return [];
        }

        $sessions = $this->applyActorScopeToSessions(TimerSession::query())
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNotNull('stopped_at')
            ->with(['task.project'])
            ->get(['id', 'invoice_id', 'user_id', 'task_id', 'duration_seconds']);

        $userRateMap = $this->userChargeOutRateMapForIds(
            $sessions->pluck('user_id')->filter()->map(fn ($userId): int => (int) $userId)->unique()->values()->all()
        );

        $projectTotals = [];

        foreach ($sessions as $session) {
            $invoiceId = (int) ($session->invoice_id ?? 0);
            $invoiceRate = $convertedInvoiceRates[$invoiceId] ?? null;

            if ($invoiceRate === null) {
                continue;
            }

            $project = optional(optional($session->task)->project);
            $projectId = $project->id ? (int) $project->id : null;
            $projectName = $project->name ?: 'Unassigned Project';
            $projectKey = $projectId !== null ? 'project-' . $projectId : 'project-unassigned';
            $durationSeconds = max(0, (int) ($session->duration_seconds ?? 0));

            if (!array_key_exists($projectKey, $projectTotals)) {
                $projectTotals[$projectKey] = [
                    'project_id' => $projectId,
                    'project_name' => $projectName,
                    'sessions_count' => 0,
                    'total_duration_seconds' => 0,
                    'billable_time_amount_converted' => 0.0,
                ];
            }

            $projectTotals[$projectKey]['sessions_count'] += 1;
            $projectTotals[$projectKey]['total_duration_seconds'] += $durationSeconds;
            $sessionUserId = $session->user_id !== null ? (int) $session->user_id : null;
            $resolvedHourlyRate = $this->resolveSessionHourlyRate(
                $sessionUserId,
                (float) ($invoiceRate['client_hourly_rate'] ?? 0.0),
                $userRateMap
            );

            $projectTotals[$projectKey]['billable_time_amount_converted'] += ($durationSeconds / 3600)
                * $resolvedHourlyRate
                * (float) $invoiceRate['conversion_rate'];
        }

        return collect($projectTotals)
            ->map(function (array $project): array {
                $project['billable_time_amount_converted'] = round((float) $project['billable_time_amount_converted'], 2);
                return $project;
            })
            ->sortBy(fn (array $project): string => strtolower((string) ($project['project_name'] ?? '')))
            ->values()
            ->all();
    }

    /**
     * @param array<string, float|null> $rateCache
     */
    private function invoiceConversionRateToTarget(Invoice $invoice, ?string $sourceCurrency, string $targetCurrency, array &$rateCache): ?float
    {
        if ($sourceCurrency === null) {
            return null;
        }

        if ($sourceCurrency === $targetCurrency) {
            return 1.0;
        }

        $storedTarget = $this->normalizeCurrencyCode($invoice->conversion_target_currency);
        $storedRate = (float) ($invoice->conversion_rate ?? 0);

        if ($storedRate > 0 && $storedTarget === $targetCurrency) {
            return $storedRate;
        }

        return $this->resolveCurrencyRateOrNull($sourceCurrency, $targetCurrency, $rateCache);
    }

    /**
     * @param array<string, float|null> $rateCache
     */
    private function resolveCurrencyRateOrNull(string $sourceCurrency, string $targetCurrency, array &$rateCache): ?float
    {
        if ($sourceCurrency === $targetCurrency) {
            return 1.0;
        }

        $cacheKey = $sourceCurrency . ':' . $targetCurrency;

        if (array_key_exists($cacheKey, $rateCache)) {
            $cachedRate = $rateCache[$cacheKey];

            return $cachedRate !== null ? (float) $cachedRate : null;
        }

        $liveRate = $this->fetchWiseLiveRate($sourceCurrency, $targetCurrency);

        if ($liveRate === null) {
            $rateCache[$cacheKey] = null;
            return null;
        }

        $resolvedRate = (float) $liveRate['rate'];
        $rateCache[$cacheKey] = $resolvedRate;

        return $resolvedRate > 0 ? $resolvedRate : null;
    }

    private function findOrCreateFinancialYearForTeam(int $userId, int $teamId, int $startYear): FinancialYear
    {
        $period = $this->nzFinancialYearPeriod($startYear);

        return FinancialYear::query()->firstOrCreate(
            [
                'team_id' => $teamId,
                'start_year' => $startYear,
            ],
            [
                'user_id' => $userId,
                'end_year' => $startYear + 1,
                'label' => $period['label'],
                'start_date' => $period['start']->toDateString(),
                'end_date' => $period['end']->toDateString(),
            ]
        );
    }

    private function calculateInvoiceDiscountAmount(float $subtotalAmount, ?string $discountType, float $discountValue): float
    {
        $normalizedType = strtolower((string) $discountType);
        $safeSubtotal = max(0, round($subtotalAmount, 2));
        $safeValue = max(0, round($discountValue, 2));

        if ($safeSubtotal <= 0 || $safeValue <= 0) {
            return 0.0;
        }

        if ($normalizedType === 'percentage') {
            $boundedRate = min(100, $safeValue);
            return round($safeSubtotal * ($boundedRate / 100), 2);
        }

        if ($normalizedType === 'fixed') {
            return round(min($safeSubtotal, $safeValue), 2);
        }

        return 0.0;
    }

    private function formatInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['client', 'financialYear']);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $invoice->client_id,
            'financial_year_id' => $invoice->financial_year_id,
            'conversion_source_currency' => $invoice->conversion_source_currency,
            'conversion_target_currency' => $invoice->conversion_target_currency,
            'conversion_rate' => $invoice->conversion_rate,
            'conversion_rate_fetched_at' => $invoice->conversion_rate_fetched_at,
            'financial_year' => $invoice->financialYear ? [
                'id' => $invoice->financialYear->id,
                'label' => $invoice->financialYear->label,
                'start_year' => $invoice->financialYear->start_year,
                'end_year' => $invoice->financialYear->end_year,
                'start_date' => $invoice->financialYear->start_date ? $invoice->financialYear->start_date->toDateString() : null,
                'end_date' => $invoice->financialYear->end_date ? $invoice->financialYear->end_date->toDateString() : null,
            ] : null,
            'client' => $invoice->client ? [
                'id' => $invoice->client->id,
                'name' => $invoice->client->name,
                'email' => $invoice->client->email,
                'currency' => $invoice->client->currency,
                'hourly_rate' => $invoice->client->hourly_rate,
                'notes' => $invoice->client->notes,
            ] : null,
            'status' => $invoice->status,
            'discount_type' => $invoice->discount_type,
            'discount_value' => (float) ($invoice->discount_value ?? 0),
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
