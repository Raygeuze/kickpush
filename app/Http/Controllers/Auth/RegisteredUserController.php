<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Team;
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'country' => 'required|string|size:2|alpha',
            'invitation' => 'nullable|integer',
            'account_type' => 'nullable|in:individual,business',
            'trading_name' => 'nullable|string|max:255',
            'business_name' => 'nullable|string|max:255',
        ]);

        $pendingInvitations = TeamInvitation::query()
            ->where('email', strtolower((string) $request->email))
            ->get();

        if ($pendingInvitations->isEmpty()) {
            $request->validate([
                'account_type' => 'required|in:individual,business',
                'trading_name' => 'nullable|required_if:account_type,individual|string|max:255',
                'business_name' => 'nullable|required_if:account_type,business|string|max:255',
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => strtolower((string) $request->email),
            'country' => strtoupper((string) $request->country),
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

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
        } else {
            $this->createOwnedTeam($user, [
                'account_type' => (string) $request->input('account_type', 'individual'),
                'trading_name' => (string) $request->input('trading_name', ''),
                'business_name' => (string) $request->input('business_name', ''),
            ]);
        }

        return redirect()->intended(route('dashboard', [], false));
    }

    /**
     * Create and select the user's primary team.
     *
     * @param  array{account_type:string,trading_name:string,business_name:string}  $registration
     */
    private function createOwnedTeam(User $user, array $registration): void
    {
        $accountType = $registration['account_type'];
        $teamName = $accountType === 'business'
            ? trim($registration['business_name'])
            : trim($registration['trading_name']);

        if ($teamName === '') {
            $teamName = $user->name;
        }

        $team = $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => $teamName,
            'personal_team' => false,
        ]));

        $user->switchTeam($team);
    }
}
