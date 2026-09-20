<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->session()->get('saklshi_customer_id', 0);
        $user = $userId > 0 ? User::query()->whereKey($userId)->where('active', true)->first() : null;

        if (! $user) {
            $request->session()->forget('saklshi_customer_id');

            return redirect()->route('account.login')->with('intended', $request->fullUrl());
        }

        $request->attributes->set('customer', $user);

        return $next($request);
    }
}
