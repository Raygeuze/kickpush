<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function search(Request $request)
    {

        $query = $request->input('query');

        $submissions = \App\Models\Submission::where('title', 'like', '%' . $query . '%')
            ->orWhere('description', 'like', '%' . $query . '%')
            ->with('user')
            ->withCount('comments')
            ->paginate(2);

        $days = \App\Models\Day::whereHas('topic', function($db_query) use ($query) {
                $db_query->where('topic', 'like', '%' . $query . '%')
                      ->orWhere('description', 'like', '%' . $query . '%');
            })
            ->orWhere('id', 'like', '%' . $query . '%')
            ->withCount('submissions')
            ->withCount('comments')
            ->with('topic')
            ->with('prizePool')
            ->paginate(2);

        $users = \App\Models\User::where('name', 'like', '%' . $query . '%')
            ->where('is_admin', false)
            ->withCount('submissions')
            ->paginate(2);

        
        return Inertia::render('Search/SearchResults', [
            'submissions' => $submissions,
            'days' => $days,
            'users' => $users,
            'query' => $query,
        ]);
    }

    public function loadMoreDays(Request $request)
    {
        $query = $request->input('query');
        $page = $request->input('page');
        $perPage = 2;

        $days = \App\Models\Day::where('topic', 'like', '%' . $query . '%')
            ->orWhere('description', 'like', '%' . $query . '%')
            ->withCount('submissions')
            ->withCount('comments')
            ->with('prizePool')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($days);
    }

    public function loadMoreSubmissions(Request $request)
    {
        $query = $request->input('query');
        $page = $request->input('page');
        $perPage = 2;

        $submissions = \App\Models\Submission::where('title', 'like', '%' . $query . '%')
            ->orWhere('description', 'like', '%' . $query . '%')
            ->with('user')
            ->withCount('comments')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($submissions);
    }

    public function loadMoreUsers(Request $request)
    {
        $query = $request->input('query');
        $page = $request->input('page');
        $perPage = 2;

        $users = \App\Models\User::where('name', 'like', '%' . $query . '%')
            ->where('is_admin', false)
            ->withCount('submissions')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($users);
    }
}
