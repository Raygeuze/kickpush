<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;
use App\Models\UserAdditionalTax;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $currentTeam = $user ? $user->currentTeam : null;
        $currentTeamRole = ($user && $currentTeam) ? $user->teamRole($currentTeam) : null;
        $isCurrentTeamEmployee = $currentTeamRole ? ((string) $currentTeamRole->key === 'employee') : false;
        $currentTeamId = $currentTeam ? (int) $currentTeam->id : null;
        $allTeams = $user ? $user->allTeams()->map(fn ($team) => [
            'id' => (int) $team->id,
            'name' => (string) $team->name,
            'personal_team' => (bool) $team->personal_team,
        ])->values()->all() : [];
        $teamAdditionalTaxes = $currentTeamId !== null
            ? UserAdditionalTax::query()
                ->where('team_id', $currentTeamId)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'name', 'category', 'value_type', 'value', 'currency', 'position'])
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                    'category' => (string) $item->category,
                    'value_type' => (string) $item->value_type,
                    'value' => (float) $item->value,
                    'currency' => $item->currency ? (string) $item->currency : null,
                    'position' => (int) $item->position,
                ])
                ->values()
                ->all()
            : [];

        return array_merge(parent::share($request), [
            'jetstream' => [
                'hasTeamFeatures' => Jetstream::hasTeamFeatures(),
                'canCreateTeams' => $user ? Gate::forUser($user)->check('create', Jetstream::newTeamModel()) : false,
                'canUpdateProfileInformation' => Features::enabled(Features::updateProfileInformation()),
                'canUpdatePassword' => Features::enabled(Features::updatePasswords()),
                'canManageTwoFactorAuthentication' => Features::enabled(Features::twoFactorAuthentication()),
                'hasAccountDeletionFeatures' => Jetstream::hasAccountDeletionFeatures(),
                'hasApiFeatures' => Jetstream::hasApiFeatures(),
                'managesProfilePhotos' => Jetstream::managesProfilePhotos(),
            ],
            'auth' => [
                'user' => $user
                    ? array_merge($user->toArray(), [
                        'current_team' => $currentTeam ? [
                            'id' => (int) $currentTeam->id,
                            'name' => (string) $currentTeam->name,
                            'personal_team' => (bool) $currentTeam->personal_team,
                            'role' => $currentTeamRole ? (string) $currentTeamRole->key : null,
                            'is_employee' => $isCurrentTeamEmployee,
                            'can_manage_non_timer_records' => ! $isCurrentTeamEmployee,
                            'is_owner' => $user->ownsTeam($currentTeam),
                            'can_manage_settings' => Gate::forUser($user)->check('update', $currentTeam),
                            'can_transfer_ownership' => $user->ownsTeam($currentTeam),
                        ] : null,
                        'all_teams' => $allTeams,
                        'additional_taxes' => $teamAdditionalTaxes,
                    ])
                    : null,
            ],
        ]);
    }
}
