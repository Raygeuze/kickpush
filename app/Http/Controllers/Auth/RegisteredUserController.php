<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'invitation' => $request->query('invitation'),
            'invitedEmail' => $request->query('email'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|lowercase|max:255|alpha_num|unique:'.User::class,
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'country' => 'required|string|size:2|alpha',
            'invitation' => 'nullable|integer',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'country' => strtoupper((string) $request->country),
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $pendingInvitations = TeamInvitation::query()
            ->where('email', $user->email)
            ->get();

        if ($pendingInvitations->isNotEmpty()) {
            $requestedInvitationId = $request->integer('invitation');
            $orderedInvitations = $requestedInvitationId > 0
                ? $pendingInvitations->sortByDesc(fn (TeamInvitation $invitation) => (int) $invitation->id === $requestedInvitationId)->values()
                : $pendingInvitations;

            foreach ($orderedInvitations as $invitation) {
                app(AddsTeamMembers::class)->add(
                    $invitation->team->owner,
                    $invitation->team,
                    $invitation->email,
                    $invitation->role,
                );

                $invitation->delete();
            }

            $primaryInvitation = $orderedInvitations->first();

            if ($primaryInvitation !== null) {
                $user->switchTeam($primaryInvitation->team);
            }
        }

        return redirect()->intended(config('fortify.home'));
    }
}
