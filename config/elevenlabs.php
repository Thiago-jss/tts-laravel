<?php

/**
 * Configuração da API ElevenLabs
 *
 * Este arquivo centraliza todas as configurações relacionadas à integração
 * com a ElevenLabs API para Text-to-Speech.
 *
 * Referência da API: https://api.elevenlabs.io/docs
 */

return [
    /*
    |--------------------------------------------------------------------------
    | ElevenLabs API Key
    |--------------------------------------------------------------------------
    |
    | Sua chave de API da ElevenLabs. Obtenha em:
    | https://elevenlabs.io/app/settings/api-keys
    |
    | IMPORTANTE: Nunca commite esta chave! Use .env e secrets no CI/CD.
    |
    */
    'api_key' => env('ELEVEN_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | ElevenLabs Voice ID
    |--------------------------------------------------------------------------
    |
    | ID da voz padrão para síntese. Lista de vozes disponíveis:
    | GET https://api.elevenlabs.io/v1/voices
    |
    | Vozes pré-instaladas populares:
    | - 21m00Tcm4TlvDq8ikWAM (Rachel - English Female)
    | - EXAVITQu4vr4xnSDxMaL (Bella - English Female)
    | - ErXwobaYiN019PkySvjV (Antoni - English Male)
    |
    */
    'voice_id' => env('ELEVEN_VOICE_ID', '21m00Tcm4TlvDq8ikWAM'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | URL base da API ElevenLabs. Normalmente não precisa alterar.
    |
    */
    'base_url' => env('ELEVEN_API_BASE_URL', 'https://api.elevenlabs.io/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout em segundos para requests HTTP à API.
    | ElevenLabs pode demorar 2-10s dependendo do tamanho do texto.
    |
    */
    'timeout' => env('ELEVEN_API_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Model ID
    |--------------------------------------------------------------------------
    |
    | Modelo de TTS a usar. Opções:
    | - eleven_monolingual_v1 (inglês, mais rápido)
    | - eleven_multilingual_v1 (suporta múltiplos idiomas)
    | - eleven_multilingual_v2 (melhor qualidade, mais lento)
    |
    */
    'model_id' => env('ELEVEN_MODEL_ID', 'eleven_multilingual_v2'),

    /*
    |--------------------------------------------------------------------------
    | Voice Settings
    |--------------------------------------------------------------------------
    |
    | Configurações de voz (stability, similarity_boost, style, use_speaker_boost).
    | Valores entre 0.0 e 1.0.
    |
    */
    'voice_settings' => [
        'stability' => 0.5,
        'similarity_boost' => 0.75,
        'style' => 0.0,
        'use_speaker_boost' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audio Storage
    |--------------------------------------------------------------------------
    |
    | Configurações de armazenamento dos arquivos de áudio gerados.
    |
    */
    'storage' => [
        'disk' => 'public',
        'path' => 'audio',
        // Tempo de vida dos arquivos em minutos (0 = nunca expira)
        'ttl' => env('ELEVEN_AUDIO_TTL', 60), // 1 hora
    ],
];

