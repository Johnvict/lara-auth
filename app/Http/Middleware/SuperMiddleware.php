<?php

namespace App\Http\Middleware;

use App\Services\ResponseFormat;
use Closure;
use Illuminate\Support\Facades\Auth;

class SuperMiddleware
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
		if (Auth::user()->type != "super") return self::returnNotPermitted();
		return $next($request);
	}
}
