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
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'income_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'student_loan_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'additional_taxes' => ['nullable', 'array', 'max:50'],
            'additional_taxes.*.name' => ['required', 'string', 'max:120'],
            'additional_taxes.*.category' => ['required', Rule::in(['tax', 'levy', 'allocation'])],
            'additional_taxes.*.value_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'additional_taxes.*.value' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'additional_taxes.*.currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'additional_taxes.*.position' => ['nullable', 'integer', 'min:0'],
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
            DB::transaction(function () use ($user, $input, $incomeTaxRate, $studentLoanTaxRate): void {
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

                $this->syncAdditionalTaxes($user, $input);
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
                'income_tax_rate' => isset($input['income_tax_rate']) ? (float) $input['income_tax_rate'] : 0,
                'student_loan_tax_rate' => isset($input['student_loan_tax_rate']) ? (float) $input['student_loan_tax_rate'] : 0,
                'bank_account_name' => $input['bank_account_name'] ?? null,
                'bank_name' => $input['bank_name'] ?? null,
                'bsb_code' => $input['bsb_code'] ?? null,
                'bank_account_number' => $input['bank_account_number'] ?? null,
                'email_verified_at' => null,
            ])->save();

            $this->syncAdditionalTaxes($user, $input);
        });

        $user->sendEmailVerificationNotification();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function syncAdditionalTaxes(User $user, array $input): void
    {
        $rows = collect($input['additional_taxes'] ?? [])
            ->values()
            ->map(function ($item, int $index): array {
                $name = trim((string) ($item['name'] ?? ''));
                $category = strtolower((string) ($item['category'] ?? 'tax'));
                $valueType = strtolower((string) ($item['value_type'] ?? 'percentage'));
                $value = round((float) ($item['value'] ?? 0), 2);
                $currency = strtoupper(trim((string) ($item['currency'] ?? '')));
                $position = isset($item['position']) ? (int) $item['position'] : $index;

                return [
                    'name' => $name,
                    'category' => in_array($category, ['tax', 'levy', 'allocation'], true) ? $category : 'tax',
                    'value_type' => in_array($valueType, ['percentage', 'fixed'], true) ? $valueType : 'percentage',
                    'value' => $value,
                    'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null,
                    'position' => max(0, $position),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '' && $row['value'] >= 0)
            ->values();

        $user->additionalTaxes()->delete();

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        $user->additionalTaxes()->insert(
            $rows->map(fn (array $row): array => [
                'user_id' => $user->id,
                'name' => $row['name'],
                'category' => $row['category'],
                'value_type' => $row['value_type'],
                'value' => $row['value'],
                'currency' => $row['value_type'] === 'fixed' ? $row['currency'] : null,
                'position' => $row['position'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }
}
