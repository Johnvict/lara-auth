<?php

namespace App\Http\Middleware;

use App\Services\ResponseFormat;
use Closure;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    use ResponseFormat;

    public function handle($request, Closure $next)
    {
        return Auth::user()->type == "admin" || Auth::user()->type == "super" ?  $next($request) : self::returnNotPermitted();
    }
}
