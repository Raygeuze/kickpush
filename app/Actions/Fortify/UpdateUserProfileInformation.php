<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bsb_code' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            DB::transaction(function () use ($user, $input): void {
                $user->forceFill([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'country' => strtoupper((string) $input['country']),
                    'bank_account_name' => $input['bank_account_name'] ?? null,
                    'bank_name' => $input['bank_name'] ?? null,
                    'bsb_code' => $input['bsb_code'] ?? null,
                    'bank_account_number' => $input['bank_account_number'] ?? null,
                ])->save();
            });
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        DB::transaction(function () use ($user, $input): void {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'country' => strtoupper((string) $input['country']),
                'bank_account_name' => $input['bank_account_name'] ?? null,
                'bank_name' => $input['bank_name'] ?? null,
                'bsb_code' => $input['bsb_code'] ?? null,
                'bank_account_number' => $input['bank_account_number'] ?? null,
                'email_verified_at' => null,
            ])->save();
        });

        $user->sendEmailVerificationNotification();
    }
}
