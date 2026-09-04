<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

use App\Http\Controllers\Api\InternalLexiconController;

Route::prefix('v1/internal')->group(function () {
    Route::get('/lexicon/snapshot', [InternalLexiconController::class, 'getSnapshot']);
});
