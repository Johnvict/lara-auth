<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return redirect()->route('health');
});

$router->group(['prefix' => 'api/v1'], function () use ($router) {
    $router->get('/', function () use ($router) {
        return redirect()->route('health');
    });
    // ? APP STATUS
    $router->get('/health', [
        'as' => 'health',
        'middleware' => 'appState',
        function () {
            return response()->json();
        }
    ]);


    // ? ADMIN ROUTES
    $router->group(['prefix' => 'admin'], function () use ($router) {
        $router->post('/login', 'AuthController@loginAdmin');

        $router->group(['middleware' => ['auth', 'super']], function () use ($router) {
            $router->delete('/delete-admin', 'AdminController@deleteAdmin');
            $router->post('/register', 'AuthController@registerAdmin');
        });

        $router->group(['middleware' => ['auth', 'admin']], function () use ($router) {
            $router->post('/change-password', 'AuthController@changePassword');
            $router->get('/enable', 'AdminController@enable');
            $router->get('/disable', 'AdminController@disable');

            $router->post('/get-users', 'AdminController@getUsers');
            $router->get('/user-details/{id}', 'AdminController@getUsersDetails');
            $router->post('/get-admins', 'AdminController@getAdmins');
            $router->post('/activate-user', 'AdminController@activateUser');
            $router->post('/deactivate-user', 'AdminController@deactivateUser');
            $router->post('/activate-admin', 'AdminController@activateAdmin');
            $router->post('/deactivate-admin', 'AdminController@deactivateAdmin');
        });
    });

    // ? USER AUTH ROUTES
    $router->group(['prefix' => 'user'], function () use ($router) {
        $router->post('/register', 'AuthController@registerUser');
        $router->post('/login', 'AuthController@loginUser');
        $router->group(['middleware' => ['auth', 'user']], function () use ($router) {
            $router->post('/update', 'AuthController@updateUser');
        });
    });


    // ? GENERAL AUTH ROUTES
    $router->group(['prefix' => 'auth'], function () use ($router) {
        $router->group(['middleware' => 'user'], function () use ($router) {
            $router->post('/change-password', 'AuthController@changePassword');
            $router->get('/refresh', 'AuthController@refreshToken');
        });

        $router->get('/logout', 'AuthController@logout');

        // ? OTHER AUTH-RELATED ROUTES
        $router->post('/verify-account', 'AuthController@verifyAccount');
        $router->get('/reset-password/{phoneNumber}', 'AuthController@resetPassword');
        $router->post('/new-password', 'AuthController@newPassword');
    });
});
