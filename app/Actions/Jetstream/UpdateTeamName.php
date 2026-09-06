<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Contracts\UpdatesTeamNames;

class UpdateTeamName implements UpdatesTeamNames
{
    /**
     * Validate and update the given team's name.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, Team $team, array $input): void
    {
        Gate::forUser($user)->authorize('update', $team);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'required', 'string', Rule::in(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))],
        ])->validateWithBag('updateTeamName');

        $attributes = ['name' => $validated['name']];

        if (array_key_exists('timezone', $validated)) {
            $attributes['timezone'] = $validated['timezone'];
        }

        $team->forceFill($attributes)->save();
    }
}
