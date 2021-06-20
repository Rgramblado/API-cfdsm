<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KlinesController;
use App\Http\Controllers\MarketsController;
use App\Http\Controllers\OperationsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/auth/register', [AuthController::class, 'register']);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/klines/last', [KlinesController::class, 'getLastKline']);

Route::get('/klines', [KlinesController::class, 'getKlines']);

Route::get('/ticker/24h', [MarketsController::class, 'getTicker']);

Route::get('/markets', [KlinesController::class, 'getMarketsUTCData']);

Route::get('/market', [MarketsController::class, 'getMarket']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    
    Route::get('/me', function(Request $request) {
        return auth()->user();
    });

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/user/operations/current', [OperationsController::class, 'currentIndex']);

    Route::get('/user/operations/pending', [OperationsController::class, 'pendingIndex']);
    
    Route::get('/user/operations/historical', [OperationsController::class, 'historicalIndex']);

    Route::put('/user/operation',  [OperationsController::class, 'addOperation']);
    
    Route::delete('/user/operation',  [OperationsController::class, 'closeOperation']);

});
