<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsNotDisabled
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::user()->disabled) {
            return redirect('/user/profile')->with('error', 'Your account is disabled.');
        }

        return $next($request);
    }
}