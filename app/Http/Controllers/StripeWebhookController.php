<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('stripe-signature');
        $webhookSecret = config('services.stripe.webhook.secret'); // Store your webhook secret in .env

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Process the event based on its type
        switch ($event->type) {
            case 'account.updated':
                // Handle account updates
                $account = $event->data->object;

                // Determine if the account is able to accept transfers and payouts
                $capabilities = $account->capabilities ?? [];
                $capabilities['transfers'] = $capabilities['transfers'] ?? 'inactive';
                $capabilities['payouts'] = $capabilities['payouts'] ?? 'inactive';

                $user = \App\Models\User::where('stripe_account_id', $account->id)->first();

                if( $capabilities['transfers'] === 'active' && $capabilities['payouts'] === 'active' ) {
                    // The account can accept transfers and payouts
                    if ($user) {
                        $user->can_accept_payouts = true;
                        $user->save();                                        
                    }
                }
                else {
                    // The account cannot accept transfers and payouts
                    if ($user) {
                        $user->can_accept_payouts = false;
                        $user->save();                                        
                    }
                }
                break;
            // case 'payout.updated':
            //     // Handle payout updates
            //     $payout = $event->data->object;
            //     // You can update your database or notify the user about the payout status here

            //     $user = \App\Models\User::where('stripe_account_id', $payout->destination)->first();

            //     break;
            default:
                // Log or ignore unhandled events
                break;
        }

        return response()->json(['status' => 'success']);
    }
}