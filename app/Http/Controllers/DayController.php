<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DayController extends Controller
{
    
    public function show($id)
    {
        $user = Auth::user();
        $day = \App\Models\Day::with(['submissions', 'firstPlaceUser', 'secondPlaceUser', 'thirdPlaceUser', 'prizePool', 'topic'])
            ->withCount('comments')
            ->findOrFail($id);

        $submissions = $day->submissions()
            ->with('parentComments')
            ->withCount('comments')
            ->orderByDesc('votes')
            ->paginate(5);
        $todaysVoteCount = $user ? $user->todaysVoteCount($day->id) : null;

        return inertia('Day/Show', ['day' => $day, 'submissions' => $submissions, 'todaysVoteCount' => $todaysVoteCount, 'user' => $user]);
    }

    public function daySubmissions($day_id, $page = 0)
    {
        $day = \App\Models\Day::findOrFail($day_id);

        $submissions = $day->submissions()
            ->with('parentComments')
            ->withCount('comments')
            ->orderByDesc('votes')
            ->paginate(5, ['*'], 'page', $page);

        return response()->json($submissions);
    }

    public function index()
    {
        $topThreeDays = \App\Models\Day::withCount('submissions')
            ->with('topic')
            ->withCount('comments')
            ->with('prizePool')
            ->withSum('submissions', 'votes')
            ->orderByDesc('submissions_sum_votes')
            ->take(3)
            ->get();

        $days = \App\Models\Day::withCount('submissions')
            ->with('topic')
            ->withCount('comments')
            ->with('prizePool')
            ->withSum('submissions', 'votes')
            ->orderByDesc('id')->get();

        return inertia('Day/Index', ['days' => $days, 'topThreeDays' => $topThreeDays]);
    }

    // public function create()
    // {
    //     return inertia('Day/Create');
    // }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'topic' => 'required|string',
    //         'description' => 'required|string',
    //     ]);

    //     $validated['date'] = now()->toDateString();

    //     $day = \App\Models\Day::create($validated);
    //     // $day->save();

    //     $days = \App\Models\Day::withCount('submissions')->get();
    //     return inertia('Day/Index', ['days' => $days]);
    // }

    public function edit($id)
    {
        $day = \App\Models\Day::findOrFail($id);
        return inertia('Day/Edit', ['day' => $day]);
    }

    public function update(Request $request, $id)
    {
        $day = \App\Models\Day::findOrFail($id);

        $validated = $request->validate([
            'topic' => 'required|string|max:255',
            'description' => 'required|string',
            'first_place_user_id' => 'nullable|exists:users,id',
            'second_place_user_id' => 'nullable|exists:users,id',
            'third_place_user_id' => 'nullable|exists:users,id',
        ]);

        $day->update($validated);

        return redirect()->route('days.show', $day->id)->with('success', 'Day updated successfully.');
    }
}
