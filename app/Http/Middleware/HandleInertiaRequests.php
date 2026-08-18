<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

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

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user
                    ? array_merge($user->toArray(), [
                        'additional_taxes' => $user->additionalTaxes()
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
                            ->all(),
                    ])
                    : null,
            ],
        ]);
    }
}
