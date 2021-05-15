<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Models\PasswordResetCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

use App\Services\DataHelper;
use App\Models\User;
use App\Services\ResponseFormat;
use Illuminate\Support\Facades\Log;

// use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
	use DataHelper, ResponseFormat;
	/**
	 * @var \Tymon\JWTAuth\JWTAuth
	 */
	protected $jwt;

	public function __construct(JWTAuth $jwt)
	{
		$this->jwt = $jwt;
	}

	public function registerUser(Request $request)
	{
		$validationError = self::validateRequest($request, self::$registrationValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

        $userExist  = User::where('phone', $request->phone)
        ->orWhere('email', $request->phone)->first();
        if ($userExist) {
            $code = $userExist->activationCode;
			if ($userExist->is_active == false && $userExist->is_suspended == false && $code) {
                return self::returnVerifyAccount($userExist);
            } else if ($userExist->is_active == true && $userExist->is_suspended == false) {
                return self::returnAlreadyRegistered($userExist);
            }
        }

		$user 			=  new User();
		$user->name		=  $request->name;
		$user->email	=  $request->email;
		$user->password	=  Hash::make($request->password);
		$user->phone	=  $request->phone;
		$user->save();

		// unset($user["password"]);
		self::createActivationCode($user->id, $request->phone);
		return self::returnSuccess($user);
	}

	public function updateUser(Request $request)
	{
		$validationError = self::validateRequest($request, self::$updateUserValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		$phoneError = User::where([["phone", '=', $request->phone], ["id", '!=', Auth::user()->id]])->first();
		$emailError = User::where([["email", '=', $request->email], ["id", '!=', Auth::user()->id]])->first();
		if ($phoneError) return self::returnFailed(["phone" => "this phone number is taken"]);
		if ($emailError) return self::returnFailed(["email" => "this email is taken"]);

		$user 			=  Auth::user();
		$user->name		=  $request->name ?? $user->name;
		$user->email	=  $request->email ?? $user->email;
		if ($request->phone && $request->phone != $user->phone) {
			$user->is_active = false;
			self::createActivationCode($user->id, $request->phone);
		}
		$user->phone	=  $request->phone ?? $user->phone;
		$user->update();

		return self::returnSuccess($user);
	}

	public function loginUser(Request $request)
	{
		$data = [
			'phone' =>  $request->phone,
			'password' => $request->password
		];

		$validationError = self::validateRequest($request, self::$authValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		// $token = Auth::attempt($data);
		$token = auth()->claims(['type' => 'user'])->attempt($data);
		if ($token) {
			$token = self::respondWithToken($token);
			$user = Auth::user();
			if ($user->type == "user") {
				if ($user->is_active == false && $user->is_suspended == false) {
                    return self::returnVerifyAccount($user);
				} else if ($user->is_active == false && $user->is_suspended == true) {
					return self::returnFailed("Sorry, your account has been suspended");
				}

				return self::returnSuccess(["token" => $token, "user" => $user]);
			}

		}
		return self::returnInvalidUsernamePassword();
	}

	public function registerAdmin(Request $request)
	{
		$validationError = self::validateRequest($request, self::$registrationValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		$user = new User();

		$user->name			=  $request->name;
		$user->email		=  $request->email;
		$user->password		=  Hash::make($request->password);
		$user->phone		=  $request->phone;
		$user->is_active	=  1;
		$user->type			=  $request->type ?? "admin";
		$user->save();

		self::createActivationCode($user->id, $request->phone);
		// unset($user["password"]);
		return self::returnSuccess($user);
	}

	public function loginAdmin(Request $request)
	{
		$data = [
			'phone' =>  $request->phone,
			'password' => $request->password
		];

		$validationError = self::validateRequest($request, self::$authValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		// $token = Auth::attempt($data);
		$token = auth()->claims(['type' => 'admin'])->attempt($data);
		$tokenSuper = auth()->claims(['type' => 'super'])->attempt($data);

		if ($token || $tokenSuper) {
			$user = Auth::user();
			$token = self::respondWithToken($user->type == "admin" ? $token : $tokenSuper);
			if ($user->type == "admin" || $user->type == "super") {
				if ($user->is_active == false && $user->is_suspended == true) {
					return self::returnFailed("Sorry, your account has been suspended");
				}
				return self::returnSuccess(["token" => $token, "admin" => $user]);
			}
			// $payload = auth()->payload()("type");
		}
		return self::returnInvalidUsernamePassword();
	}

	public static function generateRandomSix() {
		return substr(str_shuffle("5016728349"), 0, 6);
	}

	public static function createActivationCode($id, $phoneNumber) {
        // WE DON'T WANT TO SEND SMS DURING TEST, ONLY IN PRODUCTION OR WE OTHERWISE CHANGE APP STAGE IN OUR ENV SO AS TO TEST SMS FEATURES
		$activationCode = env("APP_STAGE") == "development" ? "123456" : self::generateRandomSix();
		ActivationCode::create([
			"user_id" => $id,
			"code" => $activationCode
		]);

        if(env("APP_STAGE") == "production") AuthController::sendSMS($phoneNumber, $activationCode);
	}

	public function verifyAccount(Request $request)
	{
		$validationError = self::validateRequest($request, self::$verifyAccountValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		$user = User::where('phone', '=', $request->phone)->first();

		if ($user) {
			$code = $user->activationCode;
			if ($user->is_active == false && $user->is_suspended == false) {
				if ($code != null) {
					if ($request->code == $code->code) {
						$user->update(["is_active" => 1]);
						return self::returnSuccess(["message" => "Account verified successfuly"]);
					}
				}
				return self::returnFailed("Sorry, you sent an invalid code");
			} else {
				if ($code)$code->delete();
				return self::returnSuccess(["message" => "Account verified successfuly"]);
			}
		}

		return self::returnNotFound("Sorry, no user found with this credential");
	}


	public function resetPassword($phoneNumber)
	{
		$user = User::where('phone', '=', $phoneNumber)->first();
		if ($user) {
            // WE ONLY WANT TO ALLOW OUR USERS TO RESET PASSWORD FOR ONLY A SPECIFIED NUMBER OF TIMES PER MONTH (3 times in this case)
			$date = Carbon::parse($user->password_reset_at);
			$canReset = $user->password_reset_count <= 2 ? true : ($date->diffInDays(Carbon::now()) >= 30 ? true : false);

			if ($canReset) {
				// ? The user can reset password
				// ? Send SMS
				// $resetCode = env("APP_STAGE") == "development" ? "123456" : $activationCode = self::generateRandomSix();
				$resetCode = env("APP_STAGE") == "development" ? "123456" : self::generateRandomSix();
				if(env("APP_STAGE") == "production") self::sendSMS($phoneNumber, $resetCode);

				$code = [
					"code" 		=> $resetCode,
					"user_id"	=> $user->id,
					"expire_at"	=> Carbon::now()->addMinute(15)
				];
				$user->passwordResetCode == null ? PasswordResetCode::create($code) : $user->passwordResetCode->update($code);
				return self::returnSuccess(["message" => "password reset code sent successfuly"]);
			}
			$whenToChangePassword = 30 - $date->diffInDays(Carbon::now());
			$inDay = Carbon::now()->addDay($whenToChangePassword)->diffForHumans();

			return self::returnFailed("you cannot reset your password until " . $inDay);
		}

		return self::returnSuccess(["message" => "password reset code sent successfuly"]);
	}

    private static function sendSMS($phone, $code) {
        $username    =  env("SMS_USERNAME");
        $apikey      =  env("SMS_KEY");
        $sender      =  "KOWGO";
        $messagetext = "Kowgo: Your verification code is $code. Use it to complete your registration on Kowgo. Keep safe and do not disclose to anyone";
        $flash       =  "0";
        $url         = env("SMS_API_URL");
        $phoneForSMS = "234" . substr($phone, 1);

        $sendSMSLink  = "$url?username=$username&apikey=$apikey&sender=$sender&messagetext=$messagetext&flash=$flash&recipients=$phoneForSMS";

        Log::info("SENDING SMS VIA API");
        Log::info($sendSMSLink);

        // WE WANT TO SEND SMS TO THE USER
        // $apiResponse = APICaller::sendSMSAPI($sendSMSLink);
        // Log::info("RESPONSE FROM SENDING SMS");
        // Log::info(json_encode($apiResponse));
    }

	public function newPassword(Request $request)
	{
		$validationError = self::validateRequest($request, self::$newPasswordValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		$user = User::where('phone', '=', $request->phone)->first();
		if (!$user) return self::returnFailed("no account found with this credential");
		$code = $user->passwordResetCode;
		if ($code != null) {
			if ($request->code == $code->code) {

				if (Carbon::now() > Carbon::parse($code->expire_at)) return self::returnFailed("code expired");

				$date = Carbon::parse($user->password_reset_at);
				$shouldReset = $user->password_reset_count == 3 ? true : ($date->diffInDays(Carbon::now()) >= 30 ? true : false);
				$user->update([
					"password"				=> Hash::make($request->password),
					"password_reset_count"	=> $shouldReset ? 0 : $user->password_reset_count + 1,
					"password_reset_at"		=> Carbon::now()
				]);
				$code->delete();

				return self::returnSuccess(["message" => "password changed successfuly"]);
			}
		}
		return self::returnFailed("you sent an invalid code");
	}

	public function changePassword(Request $request)
	{
		$validationError = self::validateRequest($request, self::$ChangePasswordValidationRule);
		if ($validationError != null) return self::returnFailed($validationError);

		$user = Auth::User();
		$token = Auth::attempt([
			"phone" => $user->phone,
			"password" => $request->old_password
		]);

		if ($token) {
			$user->update([ "password"	=> Hash::make($request->password) ]);
			return self::returnSuccess(["message" => "password updated successfuly"]);
		}
		return self::returnFailed("old password not valid");
	}

	public static function logout()
	{
		auth()->logout(true);
		return self::returnSuccess();
	}

	public static function refreshToken()
	{
		$token = self::respondWithToken(auth()->refresh());
		return self::returnSuccess($token);
	}

	public static function respondWithToken($token)
	{
		return [
			'secret' => $token,
			'type' => 'bearer',
			'expires_in' => Auth::factory()->getTTL() * 60
		];
	}
}
