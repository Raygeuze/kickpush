<?php

namespace App\Http\Controllers;

use App\Models\BusinessExpense;
use App\Models\FinancialYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessExpenseController extends Controller
{
    private function currentTeamIdOrFail(): int
    {
        $user = Auth::user();

        abort_unless($user && $user->currentTeam, 403, 'Select a team to continue.');

        return (int) $user->currentTeam->id;
    }

    public function index(): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $teamId = $this->currentTeamIdOrFail();

        $expenses = BusinessExpense::query()
            ->where('team_id', $teamId)
            ->with('financialYear:id,label')
            ->latest('incurred_on')
            ->latest('created_at')
            ->get();

        return Inertia::render('BusinessExpenses/Index', [
            'businessExpenses' => $expenses->map(fn (BusinessExpense $expense) => $this->formatBusinessExpense($expense))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'amount' => 'required|numeric|min:0.01',
            'incurred_on' => 'nullable|date',
            'financial_year_id' => 'nullable|integer|exists:financial_years,id',
            'tax_deductible' => 'sometimes|boolean',
            'deductible_percentage' => 'nullable|numeric|min:0|max:100',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $isTaxDeductible = (bool) ($validated['tax_deductible'] ?? false);
        $deductiblePercentage = $isTaxDeductible
            ? number_format((float) ($validated['deductible_percentage'] ?? 100), 2, '.', '')
            : null;

        $receiptPath = null;
        $receiptOriginalName = null;

        if ($request->hasFile('receipt')) {
            [$receiptPath, $receiptOriginalName] = $this->storeReceipt($request->file('receipt'));
        }

        $financialYearId = $this->resolveFinancialYearIdForActor(
            (int) Auth::id(),
            $this->currentTeamIdOrFail(),
            isset($validated['financial_year_id']) ? (int) $validated['financial_year_id'] : null
        );

        $expense = BusinessExpense::create([
            'user_id' => (int) Auth::id(),
            'team_id' => $this->currentTeamIdOrFail(),
            'financial_year_id' => $financialYearId,
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'incurred_on' => $validated['incurred_on'] ?? null,
            'tax_deductible' => $isTaxDeductible,
            'deductible_percentage' => $deductiblePercentage,
            'receipt_path' => $receiptPath,
            'receipt_original_name' => $receiptOriginalName,
        ]);

        return response()->json([
            'message' => 'Business expense added.',
            'business_expense' => $this->formatBusinessExpense($expense->fresh()),
        ], 201);
    }

    public function update(Request $request, int $businessExpenseId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $expense = BusinessExpense::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->whereKey($businessExpenseId)
            ->first();

        abort_unless($expense !== null, 404, 'Business expense not found.');

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'amount' => 'required|numeric|min:0.01',
            'incurred_on' => 'nullable|date',
            'financial_year_id' => 'nullable|integer|exists:financial_years,id',
            'tax_deductible' => 'sometimes|boolean',
            'deductible_percentage' => 'nullable|numeric|min:0|max:100',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $isTaxDeductible = (bool) ($validated['tax_deductible'] ?? false);
        $deductiblePercentage = $isTaxDeductible
            ? number_format((float) ($validated['deductible_percentage'] ?? 100), 2, '.', '')
            : null;

        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }

            [$receiptPath, $receiptOriginalName] = $this->storeReceipt($request->file('receipt'));
            $expense->receipt_path = $receiptPath;
            $expense->receipt_original_name = $receiptOriginalName;
        }

        $expense->name = $validated['name'] ?? null;
        $expense->description = $validated['description'] ?? null;
        $expense->amount = number_format((float) $validated['amount'], 2, '.', '');
        $expense->incurred_on = $validated['incurred_on'] ?? null;
        if (array_key_exists('financial_year_id', $validated)) {
            $expense->financial_year_id = $this->resolveFinancialYearIdForActor(
                (int) Auth::id(),
                $this->currentTeamIdOrFail(),
                $validated['financial_year_id'] !== null ? (int) $validated['financial_year_id'] : null
            );
        } elseif ($expense->financial_year_id === null) {
            $expense->financial_year_id = $this->resolveFinancialYearIdForActor(
                (int) Auth::id(),
                $this->currentTeamIdOrFail(),
                null
            );
        }
        $expense->tax_deductible = $isTaxDeductible;
        $expense->deductible_percentage = $deductiblePercentage;
        $expense->save();

        return response()->json([
            'message' => 'Business expense updated.',
            'business_expense' => $this->formatBusinessExpense($expense->fresh()),
        ]);
    }

    public function destroy(int $businessExpenseId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $expense = BusinessExpense::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->whereKey($businessExpenseId)
            ->first();

        abort_unless($expense !== null, 404, 'Business expense not found.');

        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();

        return response()->json([
            'message' => 'Business expense removed.',
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function storeReceipt(UploadedFile $receipt): array
    {
        $path = $receipt->store('business-expenses/receipts', 'public');

        return [$path, $receipt->getClientOriginalName()];
    }

    private function resolveFinancialYearIdForActor(int $userId, int $teamId, ?int $requestedFinancialYearId): int
    {
        if ($requestedFinancialYearId !== null) {
            $financialYear = FinancialYear::query()
                ->where('team_id', $teamId)
                ->whereKey($requestedFinancialYearId)
                ->first();

            abort_unless($financialYear !== null, 403, 'Selected financial year does not belong to this user.');

            return (int) $financialYear->id;
        }

        $currentStartYear = $this->defaultNzFinancialYearStart();
        $period = $this->nzFinancialYearPeriod($currentStartYear);

        $financialYear = FinancialYear::query()->firstOrCreate(
            [
                'team_id' => $teamId,
                'start_year' => $currentStartYear,
            ],
            [
                'user_id' => $userId,
                'end_year' => $currentStartYear + 1,
                'label' => $period['label'],
                'start_date' => $period['start']->toDateString(),
                'end_date' => $period['end']->toDateString(),
            ]
        );

        return (int) $financialYear->id;
    }

    private function defaultNzFinancialYearStart(): int
    {
        $nowNz = CarbonImmutable::now('Pacific/Auckland');

        return $nowNz->month >= 4 ? $nowNz->year : $nowNz->subYear()->year;
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, label: string}
     */
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

    private function formatBusinessExpense(BusinessExpense $expense): array
    {
        $expense->loadMissing('financialYear:id,label');

        return [
            'id' => $expense->id,
            'financial_year_id' => $expense->financial_year_id,
            'financial_year_label' => $expense->financialYear ? $expense->financialYear->label : null,
            'name' => $expense->name,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'incurred_on' => optional($expense->incurred_on)->toDateString(),
            'tax_deductible' => (bool) $expense->tax_deductible,
            'deductible_percentage' => $expense->deductible_percentage,
            'receipt_url' => $expense->receipt_path ? Storage::url($expense->receipt_path) : null,
            'receipt_original_name' => $expense->receipt_original_name,
            'created_at' => optional($expense->created_at)->toIso8601String(),
        ];
    }
}
