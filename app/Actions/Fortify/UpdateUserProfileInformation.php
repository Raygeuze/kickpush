<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'income_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'student_loan_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bsb_code' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
        ])->validateWithBag('updateProfileInformation');

        $incomeTaxRate = isset($input['income_tax_rate']) ? (float) $input['income_tax_rate'] : 0;
        $studentLoanTaxRate = isset($input['student_loan_tax_rate']) ? (float) $input['student_loan_tax_rate'] : 0;

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'income_tax_rate' => $incomeTaxRate,
                'student_loan_tax_rate' => $studentLoanTaxRate,
                'bank_account_name' => $input['bank_account_name'] ?? null,
                'bank_name' => $input['bank_name'] ?? null,
                'bsb_code' => $input['bsb_code'] ?? null,
                'bank_account_number' => $input['bank_account_number'] ?? null,
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'income_tax_rate' => isset($input['income_tax_rate']) ? (float) $input['income_tax_rate'] : 0,
            'student_loan_tax_rate' => isset($input['student_loan_tax_rate']) ? (float) $input['student_loan_tax_rate'] : 0,
            'bank_account_name' => $input['bank_account_name'] ?? null,
            'bank_name' => $input['bank_name'] ?? null,
            'bsb_code' => $input['bsb_code'] ?? null,
            'bank_account_number' => $input['bank_account_number'] ?? null,
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
