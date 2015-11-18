<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('layouts/main');
});

Route::post('auth/login', ['as' => 'auth_sign_in', 'uses' => 'Auth\AuthController@postLogin']);
Route::get('auth/logout', 'Auth\AuthController@getLogout');

// Route::controllers([
//   'auth' => 'Auth\AuthController',
//   'password' => 'Auth\PasswordController',
// ]);
