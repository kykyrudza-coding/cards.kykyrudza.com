<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LobbyController;
use App\Http\Controllers\Api\MatchController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->prefix('lobbies')->group(function () {
    Route::post('/', [LobbyController::class, 'store']);
    Route::get('/{lobby:code}', [LobbyController::class, 'show']);
    Route::post('/{lobby:code}/join', [LobbyController::class, 'join']);
    Route::post('/{lobby:code}/leave', [LobbyController::class, 'leave']);
    Route::post('/{lobby:code}/ready', [LobbyController::class, 'ready']);
    Route::post('/{lobby:code}/start', [LobbyController::class, 'start']);
});

Route::middleware('auth:sanctum')->prefix('matches')->group(function () {
    Route::get('/{match}', [MatchController::class, 'show']);
    Route::post('/{match}/actions/hit', [MatchController::class, 'hit']);
    Route::post('/{match}/actions/stand', [MatchController::class, 'stand']);
    Route::post('/{match}/actions/double', [MatchController::class, 'double']);
    Route::post('/{match}/actions/split', [MatchController::class, 'split']);
    Route::post('/{match}/actions/attack', [MatchController::class, 'attack']);
    Route::post('/{match}/actions/translate', [MatchController::class, 'translate']);
    Route::post('/{match}/actions/defend', [MatchController::class, 'defend']);
    Route::post('/{match}/actions/take', [MatchController::class, 'take']);
    Route::post('/{match}/actions/pass', [MatchController::class, 'pass']);
    Route::post('/{match}/bet', [MatchController::class, 'placeBet']);
    Route::post('/{match}/next-round', [MatchController::class, 'nextRound']);
    Route::post('/{match}/finish', [MatchController::class, 'finish']);
});
