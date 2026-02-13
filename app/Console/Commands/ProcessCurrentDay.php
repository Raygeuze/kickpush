<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \App\Http\Controllers\StripeController;

class ProcessCurrentDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-current-day';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the current days winners and assign prizes accordingly';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('------- START PROCESS CURRENT DAY COMMAND -------');
	$day = \App\Models\Day::latest()->first();

        if (!$day) {
            $this->error('No day found to process winners.');
            return 1;
        }

        // Process winners for the day
        $this->processWinners($day);

	$this->info('------- END PROCESS CURRENT DAY COMMAND -------');

        return 0;
    }

    protected function processWinners($day)
    {
        // Logic to process winners and assign prizes
        $this->info("Process winners for Day ID: {$day->id}");

        //get top 3 submissions by votes
        $topSubmissions = $day->submissions()
            ->orderByDesc('votes')
            ->take(3)
            ->get();

        if ($topSubmissions->isEmpty()) {
            $this->info("No submissions found for Day ID: {$day->id}");
            return;
        }

        // Assign prizes to top submissions
        $prizePool = $day->prizePool;
        if (!$prizePool) {
            $this->info("No prize pool found for Day ID: {$day->id}");
            return;
        }

        $day->first_place_user_id = $topSubmissions->get(0)->user->id ?? null;
        // $day->second_place_user_id = $topSubmissions->get(1)->user->id ?? null;
        // $day->third_place_user_id = $topSubmissions->get(2)->user->id ?? null;
        $day->save();

        if (isset($topSubmissions[0])) {
            $winner = $topSubmissions[0];
            $winner->is_winner = true;
            $winner->save();
        }
        // if (isset($topSubmissions[1])) {
        //     $secondPlace = $topSubmissions[1];
        //     $secondPlace->is_second_place = true;
        //     $secondPlace->save();
        // }
        // if (isset($topSubmissions[2])) {
        //     $thirdPlace = $topSubmissions[2];
        //     $thirdPlace->is_third_place = true;
        //     $thirdPlace->save();
        // }

        $this->info("Winners assigned for Day ID: {$day->id}");

        $winning_user = $winner->user;

        // Check if user is ready to accept Stripe payouts
        $stripeController = new StripeController();
        // $transfer = $stripeController->transferWinnings($winner->user, $day);
        $canAcceptPayouts = $stripeController->checkIfReadyForPayouts($winning_user);

        $this->info("User is " . ($canAcceptPayouts ? "ready" : "not ready") . " to accept payouts.");

        // Notify winner
        $winning_user->notify(new \App\Notifications\Winner($winning_user));
    }

}

