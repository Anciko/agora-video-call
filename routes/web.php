<?php

use App\Http\Controllers\AgoraVideoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::group(['middleware' => ['auth']], function () {
    Route::get('/agora-chat', [AgoraVideoController::class, 'index']);
    Route::post('/agora/token',  [AgoraVideoController::class, 'token']);
    Route::post('/agora/call-user',  [AgoraVideoController::class, 'callUser']);

    Route::get('/agora-chat2', [AgoraVideoController::class, 'index2']);
    Route::post('/agora/token2',  [AgoraVideoController::class, 'token2']);
    Route::post('/agora/call-user2',  [AgoraVideoController::class, 'callUser2']);
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
