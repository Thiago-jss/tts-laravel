<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TTSController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rotas API para Text-to-Speech (sem proteção CSRF)
| Prefixo automático: /api/
|
| Exemplos de uso:
| - POST /api/tts (gera áudio)
| - GET  /api/voices (lista vozes)
|
*/

// Gerar áudio - COM RATE LIMITING, SEM CSRF
// URL: POST /api/tts
Route::post('/tts', [TTSController::class, 'generate'])
    ->middleware('throttle:10,1')
    ->name('api.tts.generate');

// Listar vozes disponíveis
// URL: GET /api/voices
Route::get('/voices', [TTSController::class, 'getVoices'])
    ->middleware('throttle:30,1')
    ->name('api.tts.voices');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
