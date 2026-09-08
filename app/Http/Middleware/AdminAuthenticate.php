<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('saklshi_admin', false)
            || (int) $request->session()->get('saklshi_admin_login_at', 0) <= time() - 28800) {
            $request->session()->forget(['saklshi_admin', 'saklshi_admin_login_at']);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ავტორიზაცია საჭიროა.'], 401);
            }
            return redirect()->route('admin.login');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        return $response;
    }
}
