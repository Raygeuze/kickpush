<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use App\Services\TimesheetSessionPresenter;
use App\Services\WorkEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UnitEntryController extends Controller
{
    private WorkEntryService $entries;

    private TimesheetSessionPresenter $presenter;

    public function __construct(WorkEntryService $entries, TimesheetSessionPresenter $presenter)
    {
        $this->entries = $entries;
        $this->presenter = $presenter;
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');
        Gate::authorize('create', WorkEntry::class);

        $validated = $request->validate([
            'task_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.001|max:999999',
            'entry_date' => 'nullable|date',
            'duration_minutes' => 'nullable|numeric|min:0|max:10080',
            'notes' => 'nullable|string|max:2000',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User, 401, 'Authentication required.');

        $teamId = $this->currentTeamIdOrFail();
        $invoice = isset($validated['invoice_id'])
            ? $this->findDraftInvoiceForActorOrFail((int) $validated['invoice_id'])
            : null;

        $task = $this->entries->resolveTaskForTeam($teamId, $invoice?->client_id, null, (int) $validated['task_id']);

        if (!$task) {
            return response()->json([
                'message' => 'Select a valid active task before recording units.',
            ], 422);
        }

        if (optional($task->project)->is_active === false) {
            return response()->json([
                'message' => 'Cannot record units against an archived project.',
            ], 422);
        }

        if ($error = $this->unitBillingError($task)) {
            return response()->json(['message' => $error], 422);
        }

        $entry = $this->entries->createUnitEntry(
            $user,
            $teamId,
            $task,
            $this->resolvePerformedAt($validated['entry_date'] ?? null),
            (float) $validated['quantity'],
            (int) round(((float) ($validated['duration_minutes'] ?? 0)) * 60),
            isset($validated['notes']) ? trim($validated['notes']) : null
        );

        if ($invoice) {
            $this->entries->attachToInvoice($entry, $invoice);
        } else {
            $this->entries->assignToLatestDraftInvoice($entry, $teamId, (int) $user->id);
        }

        return $this->entryResponse($entry, 'Units recorded.', 201);
    }

    public function update(Request $request, int $entryId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.001|max:999999',
        ]);

        $entry = $this->findTeamUnitEntryOrFail($entryId);
        Gate::authorize('update', $entry);

        $this->entries->updateQuantity($entry, (float) $validated['quantity']);

        return $this->entryResponse($entry, 'Quantity updated.');
    }

    /**
     * Unit pricing must be resolvable at entry time, otherwise the entry can never be billed.
     */
    private function unitBillingError(Task $task): ?string
    {
        if ($task->resolvedBillingMode() !== WorkEntry::MODE_UNIT) {
            return 'This task is not billed per unit. Change its billing mode before recording units.';
        }

        if ($task->resolvedUnitRate() === null) {
            return 'Set a rate per unit on this task before recording units.';
        }

        if ($task->resolvedUnitLabel() === null) {
            return 'Set a unit name on this task before recording units.';
        }

        return null;
    }

    private function resolvePerformedAt(?string $entryDate): CarbonImmutable
    {
        $timezone = $this->currentTeamTimezone();
        $nowLocal = CarbonImmutable::now($timezone);

        if (!$entryDate) {
            return $nowLocal->setTimezone('UTC');
        }

        $day = CarbonImmutable::parse($entryDate, $timezone)->startOfDay();

        if ($day->isSameDay($nowLocal)) {
            return $nowLocal->setTimezone('UTC');
        }

        return $day
            ->setTime($nowLocal->hour, $nowLocal->minute, $nowLocal->second)
            ->setTimezone('UTC');
    }

    private function findTeamUnitEntryOrFail(int $entryId): WorkEntry
    {
        $entry = WorkEntry::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->where('billing_mode', WorkEntry::MODE_UNIT)
            ->with(TimesheetSessionPresenter::RELATIONS)
            ->whereKey($entryId)
            ->first();

        abort_unless($entry !== null, 404, 'Unit entry not found.');

        return $entry;
    }

    private function findDraftInvoiceForActorOrFail(int $invoiceId): Invoice
    {
        $invoice = Invoice::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->with('client')
            ->whereKey($invoiceId)
            ->first();

        abort_unless($invoice !== null, 404, 'Invoice not found.');
        abort_if(in_array($invoice->status, ['finalized', 'paid'], true), 422, 'Finalized or paid invoices cannot be edited.');

        return $invoice;
    }

    private function entryResponse(WorkEntry $entry, string $message, int $status = 200): JsonResponse
    {
        $entry->refresh()->load(TimesheetSessionPresenter::RELATIONS);
        $generatedAt = now();

        return response()->json([
            'message' => $message,
            'entry' => $this->presenter->present($entry, $this->currentTeamTimezone(), $generatedAt),
            'server_now' => $generatedAt->toIso8601String(),
        ], $status);
    }

    private function currentTeamIdOrFail(): int
    {
        $user = Auth::user();

        abort_unless($user && $user->currentTeam, 403, 'Select a team to continue.');

        return (int) $user->currentTeam->id;
    }

    private function currentTeamTimezone(): string
    {
        $user = Auth::user();

        return (string) (optional(optional($user)->currentTeam)->timezone ?: 'UTC');
    }
}
