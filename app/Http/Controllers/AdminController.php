<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DataHelper;
use App\Services\ResponseFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
	use DataHelper, ResponseFormat;
	public const APP_STATE = 'APP_STATE', APP_STATE_ENABLED = 'ENABLED', APP_STATE_DISABLED = 'DISABLED';
	public static $queryArray = [];


	public function __construct()
	{
		$this->providerId = null;
	}

    	/**
	* To enable this microservice to be active/accessible
	*
	* @method GET
	* @return JSON response with status of the app and success message
	*/
	public function enable()
	{
        Log::info("ENABLING APP");
        $this->setAppState(self::APP_STATE_ENABLED);

        return ResponseFormat::returnSuccess(["app_status" => "enabled"]);
	}

	/**
	* Disable this microservice
	*
	* @method GET
	* @return JSON response with app status and success message
	*/
	public function disable()
	{
        Log::info("DISABLING APP");
        $this->setAppState(self::APP_STATE_DISABLED);

        return ResponseFormat::returnSuccess(["app_status" => "disabled"]);
	}


	/**
	* To check the health of the app
	*
	* @method GET
	* @return JSON - empty with 200 header response
	*/
	public function health()
	{
		return response()->json();
	}


	/**
	* To set the state of the Microservice by saving a file on the local directory with the status value
	*
	* @param String $appStatus - "ENABLED" || "DISABLED"
	*/
	private function setAppState($appStatus)
	{
        return Cache::put('status', $appStatus);
	}

	public function getUsers(Request $request)
	{
		$validationError = self::validateRequest($request, self::$UserDataValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		$users = self::getDataBy('user-data', $request);

		return self::returnSuccess($users);
	}
	public function getUsersDetails($id)
	{
		$user = User::where([
			['id', '=', $id],
			['type', '=', 'user']
		])->first();

		if ($user) {
			$user->savings	= $user->savings;
			$user->loans	= $user->loans;
			return self::returnSuccess($user);
		}
		return self::returnNotFound("there is no user found with this detail");

	}

	public function getAdmins(Request $request)
	{
		$validationError = self::validateRequest($request, self::$UserDataValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		DataHelper::getFields($request, 'user-data', "admin");
		$commonQuery = collect(DataHelper::$queryArray)
        ->merge([
            ["type", "=", "admin"],
            ["id", "!=", Auth::user()->id]
        ])->each(function ($query) {
			array_push(AdminController::$queryArray, $query);
		});

		if (Auth::User()->type == "super") {

			$admins = User::query()
			->where(AdminController::$queryArray)
			->orWhere([["type", "=", "super"], ["id", "!=", Auth::user()->id]])
			->orderBy("id", "desc")
			->get();
		} else {
			$admins = User::query()
			->where($commonQuery)
			->orderBy("id", "desc")
			->get();
		}

		$admins = self::getDataBy('user-data', $request, 10, "admin");
		return self::returnSuccess($admins);
	}

    public static function getDataBy($tableType, Request $request, $limit = 10, $userType = "user")
	{
		$limit = $request->limit ?? 10;
		DataHelper::getFields($request, $tableType, null, $userType);

		$skip = ($request->page - 1) * $limit;

		$history = User::where(DataHelper::$queryArray)
		->skip($skip)
		->limit($limit)
		->orderBy("id", "desc")
		->get();

		$data = [
			"items"	=> $history,
			"is_more"		=> self::checkIfRemains($history, User::query(), DataHelper::$queryArray)
		];

		return collect($data);
	}

	public function deleteAdmin(Request $request)
	{
		$validationError = self::validateRequest($request, self::$DeleteAdminValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		$admin = User::find($request->id);
		$noAdmin = "no admin is found with this credential";
		if ($admin) {
			if ($admin->id == Auth::user()->id) return self::returnNotPermitted("you are not authorized to delete this account");
			if ($admin->type != "admin" || $admin->phone != $request->phone) return self::returnFailed($noAdmin);
			$admin->delete();
			return self::returnSuccess("admin deleted successfuly");
		}

		return self::returnFailed($noAdmin);
	}

	public function activateUser(Request $request)
	{
		return self::changeUserActiveStatus($request, 1);
	}

	public function deactivateUser(Request $request)
	{
		return self::changeUserActiveStatus($request, 0);
	}

	public function activateAdmin(Request $request)
	{
		return self::changeUserActiveStatus($request, 1, "admin");
	}

	public function deactivateAdmin(Request $request)
	{
		return self::changeUserActiveStatus($request, 0, "admin");
	}

	private static function changeUserActiveStatus(Request $request, $status, $type = "user")
	{
		$validationError = self::validateRequest($request, self::$UserStatusValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		$user = User::find($request->id);

		if ($user) {
			if ($user->type == $type && $user->phone == $request->phone) {
				$user->update([
					"is_active" => $status,
					"is_suspended" => !$status
				]);
				return self::returnSuccess();
			}
		}

		return self::returnNotFound("no " . $type . " found with this credential");
	}
}
