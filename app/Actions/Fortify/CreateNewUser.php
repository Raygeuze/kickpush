<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'account_type' => ['required', 'in:individual,business'],
            'trading_name' => ['nullable', 'required_if:account_type,individual', 'string', 'max:255'],
            'business_name' => ['nullable', 'required_if:account_type,business', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            return tap(User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'account_type' => (string) ($input['account_type'] ?? 'individual'),
                'country' => strtoupper((string) $input['country']),
                'password' => Hash::make($input['password']),
            ]), function (User $user) use ($input) {
                $this->createTeam($user, $input);

                //create stripe connected account
                if (config('services.stripe.key') && config('services.stripe.secret')) {
                    $user->createStripeAccount();
                }
            });
        });
    }

    /**
     * Create and select a default team for the user.
     *
     * @param  array<string, mixed>  $input
     */
    protected function createTeam(User $user, array $input): void
    {
        $accountType = (string) ($input['account_type'] ?? 'individual');
        $teamName = $accountType === 'business'
            ? trim((string) ($input['business_name'] ?? ''))
            : trim((string) ($input['trading_name'] ?? ''));

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
