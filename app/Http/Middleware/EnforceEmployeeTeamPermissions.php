<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceEmployeeTeamPermissions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $team = $user ? $user->currentTeam : null;

        if (! $user || ! $team) {
            abort(403, 'You must belong to a team to access this area.');
        }

        if (! $user->hasTeamRole($team, 'employee')) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();

        $employeeWriteAllowedPrefixes = [
            'timer.',
            'invoices.timer.',
            'invoices.sessions.',
            'projects.notes.',
            'teams.members.chargeOutRate.',
        ];

        foreach ($employeeWriteAllowedPrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return $next($request);
            }
        }

        abort(403, 'Your role allows viewing records only.');
    }
}
