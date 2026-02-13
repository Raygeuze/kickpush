<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Submission;
use Stripe\StripeClient;
use Stripe\PaymentIntent;
use Stripe\CustomerSession;
use Webpatser\Countries\Countries;
use Stripe\Stripe as StripeGateway;
use Illuminate\Support\Str;
use Exception;
use App\Models\User;
use App\Models\Day;
use App\Models\PrizePool;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    /**
     * Create a Stripe account session for the authenticated user.
     */
    public function createAccountSession(Request $request)
    {
        $user = Auth::user();
        Log::info('Creating Stripe account session', ['user_id' => $user->id]);
        $stripe = new StripeClient(config('services.stripe.secret'));
        $accountSession = $stripe->accountSessions->create
        ([
            'account' => $user->stripe_account_id,
            'components' => [
                'account_onboarding' => 
                [
                    'enabled' => true,
                    'features' => [
                        'disable_stripe_user_authentication' => true,
                    ],
                ]
            ],
        ]);
        Log::info('Stripe account session created', ['user_id' => $user->id, 'session_id' => $accountSession->id]);
        return response()->json($accountSession);
    }

    public function initiatePayment(Request $request)
    {
        $user = Auth::user();
        Log::info('Initiating Stripe payment', ['user_id' => $user->id]);
        StripeGateway::setApiKey(config('services.stripe.secret'));
        $stripe = new StripeClient(config('services.stripe.secret'));
        try {
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'ui_mode' => 'embedded',
                'line_items' => [
                    [
                        'price' => 'price_1SM3vRRpo44haeMriEGug24m',
                        'quantity' => 1,
                    ],
                ],
                'customer' => $user->stripe_customer_id,
                'redirect_on_completion' => 'never',
            ]);
            Log::info('Stripe payment session created', ['user_id' => $user->id, 'session_id' => $session->id]);
        } catch (Exception $e) {
            Log::error('Stripe payment initiation failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
        return response()->json([
            'token' => $session->id,
            'client_secret' => $session->client_secret,
        ]);
    }

    // public function completePayment(Request $request)
    // {
    //     // $stripe = new StripeClient(config('services.stripe.secret'));

    //     // // Use the payment intent ID stored when initiating payment
    //     // $paymentDetail = $stripe->paymentIntents->retrieve('PAYMENT_INTENT_ID');

    //     // if ($paymentDetail->status != 'succeeded') {
    //     //     // throw error
    //     // }

    //     // // Complete the payment process (e.g., update database, send confirmation email, etc.)

    //     // update the associated submission to payment completed
    //     $submission = Submission::where('id', $request->input('submission_id'))->first();

    //     if ($submission) {
    //         // $submission->paid = true;
    //         $submission->save();
    //     }

    //     return true;
    // }

    public function failedPayment(Request $request)
    {
        Log::warning('Stripe payment failed', ['user_id' => Auth::id()]);
        return response()->json(['status' => 'Payment failed']);
    }

    public function transferWinnings(User $toUser, Day $day)
    {
        // Set your secret key. Remember to switch to your live secret key in production.
        // See your keys here: https://dashboard.stripe.com/apikeys
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        try {
            if ( $toUser->stripe_account_id == null ) {
                Log::error('Transfer failed: user missing Stripe account', ['user_id' => $toUser->id]);
                throw new Exception('User does not have a connected Stripe account.');
            }
            $transfer = $stripe->transfers->create([
                'amount' => $day->prizePool->total * 100,
                'currency' => 'nzd',
                'destination' => $toUser->stripe_account_id,
                'transfer_group' => $day->id,
            ]);
            Log::info('Stripe transfer successful', ['user_id' => $toUser->id, 'day_id' => $day->id, 'transfer_id' => $transfer['id']]);
            $day->transfer_complete = true;
            $day->transfer_id = $transfer['id'];
            $day->save();
            return $transfer;
        } catch (Exception $e) {
            Log::error('Stripe transfer failed', ['user_id' => $toUser->id, 'day_id' => $day->id, 'error' => $e->getMessage()]);
            $day->transfer_complete = false;
            $day->save();
            $adminUser = User::where('is_admin', true)->first();
            $adminUser->notify(new \App\Notifications\FailedTransfer('Failed transfer for user: ' . $toUser->id));
        }
    }

    public function checkIfReadyForPayouts(User $user)
    {
        Log::info('Checking if user is ready for payouts', ['user_id' => $user->id]);
        $stripe = new StripeClient(config('services.stripe.secret'));
        $account = $stripe->accounts->retrieve($user->stripe_account_id);
        if( $account->charges_enabled && $account->payouts_enabled) {
            $user->can_accept_payouts = true;
            $user->save();
            Log::info('User is ready for payouts', ['user_id' => $user->id]);
            return true;
        }
        else {
            $user->can_accept_payouts = false;
            $user->save();
            Log::info('User is NOT ready for payouts', ['user_id' => $user->id]);
            return false;
        }
    }

    public function getUsersTransferHistory(User $user)
    {
        Log::info('Fetching Stripe transfer history', ['user_id' => $user->id]);
        $stripe = new StripeClient(config('services.stripe.secret'));
        $transfers = $stripe->transfers->all([
            'destination' => $user->stripe_account_id,
        ]);
        return $transfers;
    }

    public function getUsersPayoutHistory(User $user)
    {
        Log::info('Fetching Stripe payout history', ['user_id' => $user->id]);
        $stripe = new StripeClient(config('services.stripe.secret'));
        $payouts = $stripe->payouts->all(
            [],
            ['stripe_account' => $user->stripe_account_id]
        );
        return $payouts;
    }
}
