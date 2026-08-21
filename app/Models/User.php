<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use App\Models\TimerSession;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Project;
use App\Models\UserAdditionalTax;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country',
        'stripe_account_id',
        'stripe_customer_id',
        'bank_account_name',
        'bank_name',
        'bsb_code',
        'bank_account_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function additionalTaxes()
    {
        return $this->hasMany(UserAdditionalTax::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function timerSessions()
    {
        return $this->hasMany(TimerSession::class)
            ->orderByDesc('created_at');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)
            ->orderByDesc('created_at');
    }

    public function clients()
    {
        return $this->hasMany(Client::class)
            ->orderBy('name');
    }

    public function projects()
    {
        return $this->hasMany(Project::class)
            ->orderBy('name');
    }

    public function isAdmin()
    {
        return $this->is_admin;
    }

    public function createStripeAccount()
    {
        Log::info('Creating Stripe account for user', ['user_id' => $this->id]);

        if (config('services.stripe.key') && config('services.stripe.secret')) {
            
            $stripe = new StripeClient(config('services.stripe.secret'));

            $account = $stripe->accounts->create([
                'controller' => [
                    'stripe_dashboard' => [
                        'type' => 'none'
                    ],
                    'fees' => ['payer' => 'application'],
                    'losses' => ['payments' => 'application'],
                    'requirement_collection' => 'application',
                ],
                'country' => $this->country ?? 'NZ', 
                'email' => $this->email,
                'business_type' => 'individual',
                'business_profile' => [
                    'mcc' => '5734',
                    'product_description' => 'Invoicing and timer services on the kickpush.io platform.',
                ],
                'individual' => [
                    'email' => $this->email,
                ],
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);

            // Store the Stripe account ID in the user model
            $this->stripe_account_id = $account->id;
            $this->save();

            // Create stripe customer and associate with this connected account
            // customer record is used to save payment methods for future payments
            $customer = $stripe->customers->create([
                'email' => $this->email,
                // 'name' => $this->name,
            ]);

            $this->stripe_customer_id = $customer->id;
            $this->save();

            Log::info('Stripe account created successfully', ['user_id' => $this->id, 'stripe_account_id' => $account->id]);
        }
    }

    public function hasBeenNotifiedToFinishStripeSetup()
    {
        return $this->notified_to_finish_stripe_setup;
    }

    public function markNotifiedToFinishStripeSetup()
    {
        $this->notified_to_finish_stripe_setup = true;
        $this->save();
    }
}