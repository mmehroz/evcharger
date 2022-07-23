<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainController;

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

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::any('/clear-cache', function() {
    Artisan::call('optimize');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    return response()->json("Cleared", 200);
});
Route::middleware('cors')->group(function(){
Route::any('stationlist', [MainController::class,'stationlist']);
Route::any('chargerlist', [MainController::class,'chargerlist']);
Route::any('startcharging', [MainController::class,'startcharging']);
Route::any('stopcharging', [MainController::class,'stopcharging']);
Route::any('priceunitlist', [MainController::class,'priceunitlist']);
Route::any('chargerstatus', [MainController::class,'chargerstatus']);
Route::any('websocket', [MainController::class,'websocket']);
});
