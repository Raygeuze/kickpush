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

        $validated = $request->validateWithBag('updateTeamPaymentInformation', [
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bsb_code' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
        ]);

        $team->forceFill([
            'bank_account_name' => $validated['bank_account_name'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bsb_code' => $validated['bsb_code'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
        ])->save();

        return back()->with('flash.banner', 'Team payment information updated.');
    }
}
