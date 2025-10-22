<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TTSController;

/*
|--------------------------------------------------------------------------
| Web Routes - Text-to-Speech Application
|--------------------------------------------------------------------------
|
| Rotas da aplicação TTS:
| - GET  /         -> Página inicial com formulário
| - POST /tts      -> Endpoint para gerar áudio (rate-limited)
| - GET  /voices   -> Lista vozes disponíveis (opcional)
|
| Rate Limiting:
| - Middleware throttle:10,1 = máx 10 requests por minuto por IP
| - Protege contra abuse e custos excessivos na API ElevenLabs
|
*/

// Página inicial com formulário TTS
Route::get('/', [TTSController::class, 'index'])->name('tts.index');

// Endpoint para gerar áudio - COM RATE LIMITING
// throttle:10,1 = 10 requests por 1 minuto
Route::post('/tts', [TTSController::class, 'generate'])
    ->middleware('throttle:10,1')
    ->name('tts.generate');

// Endpoint opcional para listar vozes disponíveis
Route::get('/voices', [TTSController::class, 'getVoices'])
    ->middleware('throttle:30,1')
    ->name('tts.voices');
