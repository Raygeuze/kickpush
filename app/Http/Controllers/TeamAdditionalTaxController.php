<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\UserAdditionalTax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeamAdditionalTaxController extends Controller
{
    public function update(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $validated = $request->validateWithBag('updateTeamAdditionalTaxes', [
            'additional_taxes' => ['nullable', 'array', 'max:50'],
            'additional_taxes.*.name' => ['required', 'string', 'max:120'],
            'additional_taxes.*.category' => ['required', Rule::in(['tax', 'levy', 'allocation'])],
            'additional_taxes.*.value_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'additional_taxes.*.value' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'additional_taxes.*.currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'additional_taxes.*.position' => ['nullable', 'integer', 'min:0'],
        ]);

        $rows = collect($validated['additional_taxes'] ?? [])
            ->values()
            ->map(function ($item, int $index): array {
                $name = trim((string) ($item['name'] ?? ''));
                $category = strtolower((string) ($item['category'] ?? 'tax'));
                $valueType = strtolower((string) ($item['value_type'] ?? 'percentage'));
                $value = round((float) ($item['value'] ?? 0), 2);
                $currency = strtoupper(trim((string) ($item['currency'] ?? '')));
                $position = isset($item['position']) ? (int) $item['position'] : $index;

                return [
                    'name' => $name,
                    'category' => in_array($category, ['tax', 'levy', 'allocation'], true) ? $category : 'tax',
                    'value_type' => in_array($valueType, ['percentage', 'fixed'], true) ? $valueType : 'percentage',
                    'value' => $value,
                    'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null,
                    'position' => max(0, $position),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '' && $row['value'] >= 0)
            ->values();

        DB::transaction(function () use ($request, $team, $rows): void {
            UserAdditionalTax::query()
                ->where('team_id', $team->id)
                ->delete();

            if ($rows->isEmpty()) {
                return;
            }

            $now = now();
            UserAdditionalTax::query()->insert(
                $rows->map(fn (array $row): array => [
                    'user_id' => (int) $request->user()->id,
                    'team_id' => (int) $team->id,
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'value_type' => $row['value_type'],
                    'value' => $row['value'],
                    'currency' => $row['value_type'] === 'fixed' ? $row['currency'] : null,
                    'position' => $row['position'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        });

        return back()->with('flash.banner', 'Team additional tax items updated.');
    }
}
