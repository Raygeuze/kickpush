<?php

namespace App\Http\Controllers;

use App\Models\Day;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\VoteCount;
use Inertia\Inertia;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;


class SubmissionController extends Controller
{
    //probably wouldn't use
    public function index()
    {
        $submissions = \App\Models\Submission::with('user', 'day')->get();
        return inertia('Submission/Index', ['submissions' => $submissions]);
    }

    public function indexUnapproved()
    {
        $day = \App\Models\Day::latest()->first();

        $submissions = $day->unprocessedSubmissions()->get();

        return Inertia::render('Submission/IndexUnapproved', [
            'submissions' => $submissions,
        ]);
    }

    public function show($id)
    {
        $submission = \App\Models\Submission::with('user', 'day.topic', 'parentComments', 'day.prizePool')->findOrFail($id);

        return inertia('Submission/Show', ['submission' => $submission]);
    }

    //probably wouldn't use
    public function create()
    {
        $day = \App\Models\Day::latest()->first();
        return inertia('Submission/Create', ['day' => $day]);
    }

    public function store(Request $request)
    {
        $day = Day::latest()->first();
        Log::info('Storing new submission', ['user_id' => $request->user()->id, 'day_id' => $day->id]);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'embed_link' => 'required|string'
        ]);
        $validated['user_id'] = $request->user()->id;
        $validated['day_id'] = $day->id;
        $submission = \App\Models\Submission::create($validated);
        Log::info('Submission created', ['submission_id' => $submission->id, 'user_id' => $submission->user_id, 'day_id' => $submission->day_id]);

        $this->handleCompletePayment($request, $submission, $day);

        return response()->json([
            'flashStatus' => 'success', 
            'message' => 'Submission created successfully',
            'submission' => $submission->load('user'),
        ]);
    }

    public function edit($id)
    {
        $submission = \App\Models\Submission::findOrFail($id);
        $days = \App\Models\Day::all();
        return inertia('Submission/Edit', ['submission' => $submission, 'days' => $days]);
    }

    public function update(Request $request, $id)
    {
        $submission = \App\Models\Submission::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $submission->update($validated);

        Log::info('Submission updated', ['submission_id' => $submission->id, 'user_id' => $submission->user_id]);

        return response()->json([
            'flashStatus' => 'success',
            'message' => 'Submission updated successfully.', 
            'submission' => $submission,
        ]);
    }

    public function destroy($id)
    {
        $submission = \App\Models\Submission::findOrFail($id);
        $submission->delete();

        Log::info('Submission deleted', ['submission_id' => $submission->id, 'user_id' => $submission->user_id]);

        return redirect()->route('submissions.index')->with('success', 'Submission deleted successfully.');
    }

    public function vote($id)
    {
        $user = Auth::user();
        $submission = \App\Models\Submission::findOrFail($id);
        $todaysVoteCount = $user->todaysVoteCount();

        // Check if todaysVoteCount is related to the latest Day
        $latestDay = \App\Models\Day::latest()->first();
        if ($todaysVoteCount && $todaysVoteCount->day_id !== $latestDay->id) {
            return response()->json(['flashStatus' => 'error', 'message' => 'You cannot vote on submissions from previous days', 
                'submission' => $submission,
                'todaysVoteCount' => $todaysVoteCount
            ]);
        }
        else if ($latestDay->id !== $submission->day_id) {
            return response()->json(['flashStatus' => 'error', 'message' => 'You cannot vote on submissions from previous days', 
                'submission' => $submission,
                'todaysVoteCount' => $todaysVoteCount
            ]);
        }

        if ($todaysVoteCount) {
            if ($todaysVoteCount->submissions->contains($submission->id)) {
                //remove vote
                $todaysVoteCount->submissions()->detach($submission->id);
                $submission->decrement('votes');
                $todaysVoteCount->count -= 1;

                $todaysVoteCount->save();
                $submission->save();

                return response()->json(['flashStatus' => 'success', 'message' => 'Vote removed successfully', 
                    'submission' => $submission,
                    'todaysVoteCount' => $todaysVoteCount->load('submissions')
                ]);
            }
        }

        if (!$todaysVoteCount) {

            $todaysVoteCount = new VoteCount();
            $todaysVoteCount->user_id = $user->id;
            $todaysVoteCount->day_id = $latestDay->id;
            $todaysVoteCount->save();

        } else if ($todaysVoteCount->count >= 3) {
            return response()->json(['flashStatus' => 'error', 'message' => 'You have reached your vote limit for today', 
                'submission' => $submission,
                'todaysVoteCount' => $todaysVoteCount
            ]);
        }

        $submission->increment('votes');
        $todaysVoteCount->submissions()->syncWithoutDetaching($submission->id);
        $todaysVoteCount->count += 1;
        $todaysVoteCount->save();
        $submission->save();

        Log::info('Vote recorded', ['submission_id' => $submission->id, 'user_id' => $user->id, 'todaysVoteCount_id' => $todaysVoteCount->id]);

        return response()->json(['flashStatus' => 'success', 'message' => 'Vote recorded successfully', 
            'submission' => $submission,
            'todaysVoteCount' => $todaysVoteCount->load('submissions')
        ]);
    }

    public function removeVote($id)
    {
        dd('here');
        $user = Auth::user();
        $submission = \App\Models\Submission::findOrFail($id);
        $todaysVoteCount = $user->todaysVoteCount();

        // Check if todaysVoteCount is related to the latest Day
        $latestDay = \App\Models\Day::latest()->first();
        if ($todaysVoteCount && $todaysVoteCount->day_id !== $latestDay->id) {
            return response()->json(['flashStatus' => 'error', 'message' => 'You cannot remove your votes from previous days', 
                'submission' => $submission,
                'todaysVoteCount' => $todaysVoteCount
            ]);
        }
        else if ($latestDay->id !== $submission->day_id) {
            return response()->json(['flashStatus' => 'error', 'message' => 'You cannot remove your votes from previous days', 
                'submission' => $submission,
                'todaysVoteCount' => $todaysVoteCount
            ]);
        }

        if (!$todaysVoteCount) {
            return response()->json(['flashStatus' => 'error', 'message' => 'No votes recorded for today', 'submission' => $submission]);
        }

        if ($todaysVoteCount->count <= 0) {
            return response()->json(['flashStatus' => 'error', 'message' => 'No votes to remove', 'submission' => $submission]);
        }

        $todaysVoteCount->submissions()->detach($submission->id);

        $submission->decrement('votes');
        $todaysVoteCount->count -= 1;
        $todaysVoteCount->save();
        $submission->save();

        return response()->json(['flashStatus' => 'success', 'message' => 'Vote removed successfully',
            'submission' => $submission,
            'todaysVoteCount' => $todaysVoteCount->load('submissions'),
        ]);
    }

    public function approve($id)
    {
        $submission = \App\Models\Submission::findOrFail($id);
        $submission->approve();

        Log::info('Submission approved', ['submission' => $submission]);

        return response()->json(['message' => 'Submission approved successfully']);
    }

    public function disapprove(Request $request, $id)
    {
        $reason = $request->input('reason');
        $reason = $reason ? $reason : 'No reason provided';

        $submission = \App\Models\Submission::findOrFail($id);
        $submission->disapprove($reason);

        Log::info('Submission disapproved', ['submission' => $submission, 'reason' => $reason]);

        return response()->json(['message' => 'Submission disapproved successfully']);
    }

    public function submissionHistory($id)
    {
        $submission = \App\Models\Submission::findOrFail($id);

        return Inertia::render('Submission/History', [
            'audits' => $submission->audits,
            'submission' => $submission
        ]);
    }

    public function handleCompletePayment(Request $request, Submission $submission, Day $day)
    {
        $stripe = new StripeClient(config('services.stripe.secret'));

        // Use the payment intent ID stored when initiating payment
        $sessionDetail = $stripe->checkout->sessions->retrieve($request->input('token'));

        if ($sessionDetail->status != 'succeeded') {
            // throw error
        }

        // update the associated submission to payment completed
        if ($submission) {
            $submission->paid = true;
            $submission->payment_id = $sessionDetail->payment_intent;
            $submission->save();
        }

        //increment prize pool for the day
        if($sessionDetail['presentment_details']){
            if($sessionDetail['presentment_details']['presentment_currency'] != 'nzd'){
                //international payment so stripe fees are 3.5% + .30
                $fees = ($sessionDetail['amount_total'] * 0.035) + 30;

                $amountToAdd = ($sessionDetail['amount_total'] - $fees) / 100; //convert to dollars
            } else {
                //local payment so stripe fees are 1.7% + .30
                $fees = ($sessionDetail['amount_total'] * 0.017) + 30;

                $amountToAdd = ($sessionDetail['amount_total'] - $fees) / 100; //convert to dollars
            }
        }

        // Need to minus our platform fee here too to keep things transparent
        $platformFee = 0.01 * $sessionDetail['amount_total'];
        $amountToAdd -= $platformFee / 100;

        if($day->prizePool) {
            $day->prizePool->total += $amountToAdd;
            $day->prizePool->save();
        } else {
            //shouldnt be hardcoded as 2
            $day->prizePool()->create([
                'total' => $amountToAdd,
            ]);
        }

        Log::info('Payment completed', ['submission_id' => $submission->id, 'day_id' => $day->id, 'amount' => $amountToAdd]);

        return true;
    }
}
