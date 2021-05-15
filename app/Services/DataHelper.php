<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ResponseFormat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait DataHelper
{
    public static $errorArray, $queryArray = [];
	/**
	 * ? These static values are calidation rules for all POST requests into our microservice
	 * ? They are used statically from various providers needing them
	 */

    public static $registrationValidationRule = [
		'name'      =>  'required|string|max:35',
		// 'phone'     =>  'required|numeric|digits:11|unique:users',
		// 'email'     =>  'string|email|max:50|unique:users',
		'phone'     =>  'required|numeric|digits:11',
		'email'     =>  'string|email|max:50',
		'password'  =>  'required|string|min:5',
		'type'		=>  'string|in:admin,super'
	];
	public static $updateUserValidationRule = [
		'name'      =>  'string|max:35',
		'phone'     =>  'numeric|digits:11',
		'email'     =>  'string|email|max:50'
	];

	public static $authValidationRule = [
		'phone'     =>  'required|numeric|digits:11',
		'password'	=> 'required|string'
	];
	public static $DeleteAdminValidationRule = [
		'phone'     =>  'required|numeric|digits:11',
		'id'		=> 'required|numeric|min:1'
	];

	public static $newPasswordValidationRule = [
		'phone'     =>  'required|numeric|digits:11',
		'password'	=> 'required|string',
		'code'		=> 'required|numeric|digits:6'
	];
	public static $ChangePasswordValidationRule = [
		'password'	=> 'required|string',
		'old_password' => 'required|string'
	];
	public static $verifyAccountValidationRule = [
		'phone'     =>  'required|numeric|digits:11',
		'code'		=> 'required|numeric|digits:6'
	];

	public static $UserDataValidationRule = [
		"last_id"			=> "integer",
		"joined_start_date"	=> "date|before_or_equal:today",
		"joined_end_date"	=> "date|before_or_equal:today",
		"email"				=> "email",
		"name"				=> "string",
		"phone"				=> "numeric|digits:11",
		"page"				=> "numeric|min:1"
	];
    public static $UserStatusValidationRule = [
		'phone'		=> 'required|numeric|digits:11',
		'id'		=> 'required|numeric|min:1'
	];



	/**
	 * ? To ensure a better object whose keys are the parameter keys as expected and values are the error message
	 * @param Mixed $errorArray - Complex array got from Laravel Validator method
	 * @return Mixed or null - An object is returned if there is an unexpected request body or null if no error
	 */
	public static function formatError($errorArray)
	{
		DataHelper::$errorArray = collect($errorArray);
		$newErrorFormat = DataHelper::$errorArray->map(function ($error) {
			return $error[0];
		});
		return $newErrorFormat;
	}

	/**
	 * ? To validate parameters on incoming requests
	 * ? These validation customizes the validation error
	 * @param Request $requestData - The request body as sent from the client
	 * @return Mixed or null - An object is returned if there is an unexpected request body or null if no error
	 */
	public static function validateRequest(Request $requestData, array $validationRule)
	{
		$validation = Validator::make($requestData->all(), $validationRule);

		// ? Did we get some errors? Okay, restructure the error @here
		if ($validation->fails()) return DataHelper::formatError($validation->errors());
		return false;
	}

    /**
     * We use this to further assist our custom pagination, to let our app consumer knows that there are more items left or not
     */
    public static function checkIfRemains($items, $model, $mainQuery = [])
    {

        if (count($items) > 0) {
            $extraQuery = ['id', '<', $items[count($items) - 1]->id];
            array_push($mainQuery, $extraQuery);

            return $model->where($mainQuery)->first() == null ? false : true;
        }
        return false;
    }


    public static function getFields(Request $request, $type, $userType = 'user')
	{
		DataHelper::$queryArray = [];
		switch ($type) {
			case 'user-data':
				$possibleFields = [
					"last_id" => $request->last_id,
					"joined_start_date" => $request->joined_start_date,
					"joined_end_date" => $request->joined_end_date,
					"email" => $request->email,
					"name" => $request->name,
					"phone" => $request->phone,
					"type" => $userType
				];
				break;
                // We might want to advance this app more, so we add other fields here
		}

		return collect($possibleFields)->filter(function ($field, $key) use ($possibleFields) {
			// ? WE ONLY WANT TO INCLUDE FILEDS THAT ARE INCLUDED IN OUR RULE
			return $field == null ? false : (in_array($key, array_keys($possibleFields)) == true ? true : false);
		})->map(function ($field, $key) {
			switch ($key) {
				case "phone":
					$query = ["phone", "=", $field];
					break;
				case "joined_start_date":
					$query = ["created_at", ">=", Carbon::parse($field)];
					break;
				case "joined_end_date":
					$query = ["created_at", "<=", Carbon::parse($field)];
					break;
				case "user_id":
					$query = ["user_id", "=", $field];
					break;
				case "email":
					$query = ["email", "=", $field];
					break;
				case "type":
					$query = ["type", "=", $field];
					break;
				case "name":
					$query = ["name", "=", $field];
					break;
			}

			return $query ?? null;
		})->each(function ($field) {
			array_push(DataHelper::$queryArray, [$field[0], $field[1], $field[2]]);
		});
	}

}
