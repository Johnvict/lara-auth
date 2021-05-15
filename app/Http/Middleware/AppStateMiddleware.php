<?php

namespace App\Http\Middleware;

use App\Services\ResponseFormat;
use Closure;
use Illuminate\Support\Facades\Cache;

class AppStateMiddleware
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	use ResponseFormat;
	public const APP_STATE = 'APP_STATE', APP_STATE_ENABLED = 'ENABLED', APP_STATE_DISABLED = 'DISABLED';


	public function handle($request, Closure $next)
	{
		$isAppStateAtDisabled = $this->getAppState();
		if ($isAppStateAtDisabled) return self::returnServiceDown();
		return $next($request);
	}

	public function setAppState($appState)
	{
        return Cache::put('status', $appState);
	}

	public function getAppState()
	{
        $check = Cache::has('status');

        if (!$check)
        {
            $check = $this->setAppState(self::APP_STATE_ENABLED);
            return false;
        }

        $appState = Cache::get("status");

		return $appState === self::APP_STATE_DISABLED ? true : false;
	}
}
