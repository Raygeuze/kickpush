<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamPaymentInformationController extends Controller
{
    public function update(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $country = strtoupper(trim((string) optional($team->owner)->country));
        $isAustralianCompany = $country === 'AU';

        $validated = $request->validateWithBag('updateTeamPaymentInformation', [
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bsb_code' => ['nullable', 'string', 'max:32'],
            'abn' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
        ]);

        $abn = $isAustralianCompany
            ? $this->normalizeText($validated['abn'] ?? null)
            : null;

        $team->forceFill([
            'bank_account_name' => $this->normalizeText($validated['bank_account_name'] ?? null),
            'bank_name' => $this->normalizeText($validated['bank_name'] ?? null),
            'bsb_code' => $this->normalizeText($validated['bsb_code'] ?? null),
            'abn' => $abn,
            'bank_account_number' => $this->normalizeText($validated['bank_account_number'] ?? null),
        ])->save();

        return back()->with('flash.banner', 'Team payment information updated.');
    }

    private function normalizeText(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
