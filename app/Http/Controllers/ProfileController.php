<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\StripeClient;
use \App\Http\Controllers\StripeController;


class ProfileController extends Controller
{
    public function show(): Response
    {
        $user = Auth::user();

        dd('turd');

        return Inertia::render('Profile/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Handle re-enable account request for disabled users.
     */
    public function reenableRequest(Request $request): RedirectResponse
    {
        $user = $request->user();

        //store the re-enable request
        $user->reenable_requested = true;
        $user->reenable_requested_at = now();
        $user->reenable_requested_ip = $request->ip();
        $user->reenable_requested_description = $request->input('reenable_request');
        $user->save();

        return Redirect::route('profile.show')->with('status', 'reenable-requested');
    }

    /**
     * Display the user's payments dashboard.
     */
    public function showPaymentsDetails(): Response
    {
        $user = Auth::user();

        return Inertia::render('Profile/PaymentsDetails', [
            'user' => $user,
        ]);
    }

    /**
     * Display the user's payments dashboard.
     */
    public function showPayments(): Response
    {
        $user = Auth::user();

        $usersTotalWinnings = $user->daysWon()->sum('prizePool.total');

        $usersWinningSubmissions = $user->submissions()
            ->where('is_winner', true)
            ->with('day.prizePool')
            ->get();

        $stripeController = new StripeController();

        $transfers = $stripeController->getUsersTransferHistory($user);
        $payouts = $stripeController->getUsersPayoutHistory($user);

        $hasFreshWin = $usersWinningSubmissions->contains(function ($submission) {
            return !$submission->day->transfer_complete;
        });

        //filter out from users current winning submissions which have already been paid out
        if($payouts != null && isset($payouts['data'])) {
            foreach($payouts['data'] as $payout) {
                $usersWinningSubmissions = $usersWinningSubmissions->filter(function ($submission) use ($payout) {
                    return $submission->day->prizePool->total !== $payout['amount'] / 100 && !$submission->day->transfer_complete;
                });
            }
        }

        return Inertia::render('Profile/WinningsDashboard', [
            'user' => $user,
            'usersTotalWinnings' => $usersTotalWinnings,
            'hasFreshWin' => $hasFreshWin,
            'transferHistory' => $transfers['data'] ?? [],
            'payouts' => $payouts['data'] ?? [],
            'usersWinningSubmissions' => $usersWinningSubmissions,
        ]);
    }

    // /**
    //  * Create a Stripe account session for the authenticated user.
    //  */
    // public function createAccountSession(Request $request)
    // {
    //     $user = Auth::user();

    //     $stripe = new StripeClient(config('services.stripe.secret'));

    //     $accountSession = $stripe->accountSessions->create
    //     ([
    //         'account' => $user->stripe_account_id,
    //         'components' => [
    //             'account_onboarding' => 
    //             [
    //                 'enabled' => true,
    //                 'features' => [
    //                     'disable_stripe_user_authentication' => true,
    //                     // 'requirements' => [
    //                     //     'exclude' => [
    //                     //         'business_type',
    //                     //         'business.details',
    //                     //     ],
    //                     // ],
    //                 ],
    //                 // 'collection_options' => [
    //                 //     'requirement_restrictions' => [
    //                 //         'exclude' => [
    //                 //             'business_type',
    //                 //             'business.details',
    //                 //         ],
    //                 //     ],
    //                 // ]
    //             ]
    //         ],
    //     ]);

    //     return response()->json($accountSession);
    // }
}
