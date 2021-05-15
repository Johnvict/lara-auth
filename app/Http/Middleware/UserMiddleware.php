<?php

namespace App\Http\Middleware;

use App\Services\ResponseFormat;
use Closure;
use Illuminate\Support\Facades\Auth;

class UserMiddleware
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
		if (Auth::user()) {
			if (Auth::user()->type != "user") return self::returnNotPermitted();
		} else {
			return response()->json("unauthorised", 401);
		}

		return $next($request);
	}
}
