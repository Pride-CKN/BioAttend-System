<?php

use App\Http\Controllers\AdmsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('iclock')->middleware('throttle:adms')->group(function () {
    Route::get('cdata', [AdmsController::class, 'handshake']);
    Route::post('cdata', [AdmsController::class, 'receive']);
    Route::get('getrequest', [AdmsController::class, 'getRequest']);
    Route::post('devicecmd', [AdmsController::class, 'deviceCommand']);
    Route::get('ping', [AdmsController::class, 'ping']);
});
