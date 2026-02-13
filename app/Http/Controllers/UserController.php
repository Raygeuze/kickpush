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
use Illuminate\Support\Facades\Log;

//Represents public profile of a user
class UserController extends Controller
{
    /**
     * Display the user's public profile.
     */
    public function show($id): Response
    {
        $user = \App\Models\User::with('submissions.user', 'behaviourReports.reportedBy', 'behaviourReports.reportedUser')
            ->with(['submissions' => function ($query) {
                        $query->withCount('comments');
                    }])
            ->withCount('submissions')
            ->withSum('submissions', 'votes')
            ->withCount('comments')
            ->withCount(['submissions as winning_submissions_count' => function ($query) {
                $query->where('is_winner', true);
            }])
            ->findOrFail($id);

        return Inertia::render('User/Show', [
            'user' => $user
        ]);
    }

    /**
     * Display all user for admin viewing
     */
    public function adminIndex(): Response
    {
        $users = \App\Models\User::with('submissions')->where('is_admin', false)->get();

        return Inertia::render('Admin/User/Index', [
            'users' => $users
        ]);
    }

    /**
     * Display the user's public profile for an admin to view and edit
     */
    public function adminShow($id): Response
    {
        $user = \App\Models\User::with('submissions')->findOrFail($id);

        $adminUser = Auth::guard('admin')->user();

        return Inertia::render('Admin/User/Show', [
            'user' => $user,
            'adminUser' => $adminUser
        ]);
    }

    public function disable($id)
    {
        $user = \App\Models\User::findOrFail($id);
        $user->disabled = true;

        Log::info('User disabled', ['user_id' => $user->id]);

        $user->save();

        return response()->json(['flashStatus' => 'success', 'message' => 'User disabled successfully.', 
            'user' => $user,
        ]);
    }

    public function enable($id)
    {
        $user = \App\Models\User::findOrFail($id);
        $user->disabled = false;

        Log::info('User enabled', ['user_id' => $user->id]);

        $user->save();

        return response()->json(['flashStatus' => 'success', 'message' => 'User enabled successfully.', 
            'user' => $user,
        ]);
    }

}
