<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \App\Http\Controllers\StripeController;
use Illuminate\Support\Facades\Log;

class ProcessTransfers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-transfers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process transfers to users who have won prizes over 7 days ago';

    /**
     * Execute the console command.
     */
    public function handle()
    {
	$this->info('------- START PROCESS TRANSFERS COMMAND -------');
        // $day = \App\Models\Day::latest()->first();

        $daysToProcess = \App\Models\Day::where('date', '<=', now()->subDays(8)->toDateString())
            ->where('transfer_complete', false)
            ->where('first_place_user_id', '!=', null)
            ->orderBy('date')
            ->get();

        if (!$daysToProcess) {
            $this->error('No day found to process transfers.');
            return 1;
        }

        // Process transfers for all days still owing
        foreach ($daysToProcess as $day) {
            $this->processTransfer($day);
        }

	$this->info('------- END PROCESS TRANSFERS COMMAND -------');

        return 0;
    }

    protected function processTransfer($day)
    {
        $this->info("Attempt transfer for Day ID: {$day->id}");

        $stripeController = new StripeController();
        $winning_user = $day->firstPlaceUser;

        //check user is ready to accept transfers/payouts
        $canAcceptPayouts = $stripeController->checkIfReadyForPayouts($winning_user);

        $this->info("User ID: {$winning_user->id} - User is " . ($canAcceptPayouts ? "ready" : "not ready") . " to accept payouts.");

        $transfer = $stripeController->transferWinnings($winning_user, $day);

        if($transfer){
            $this->info("Transfer attempted for User ID: {$winning_user->id}, Amount: {$transfer['amount']}");

            if( !$canAcceptPayouts ) {
                if( !$winning_user->hasBeenNotifiedToFinishStripeSetup() ) {
                    $winning_user->notify(new \App\Notifications\FinishStripeSetup($winning_user));
                    $this->info("Notification sent to User ID: {$winning_user->id} to complete Stripe setup.");
                    $winning_user->markNotifiedToFinishStripeSetup();
                }
            }
        }

        // if($transfer){
        //     if ($transfer['status'] === 'success') {
        //         $this->info("Transfer successful for User ID: {$winning_user->id}, Amount: {$transfer['amount']}");

        //         // Mark day as transfer complete
        //         $day->transfer_complete = true;
        //         $day->save();
        //     } else {
        //         $this->error("Transfer failed for User ID: {$winning_user->id}, Error: {$transfer['message']}");

        //         $day->transfer_complete = false;
        //         $day->save();
        //     }
        // }

    }

}

