<?php

namespace App\Http\Controllers;

use App\Services\ElevenLabsService;
use App\Exceptions\ElevenLabsException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para Text-to-Speech
 *
 * Endpoints:
 * - GET  /          -> Exibe formulário (view)
 * - POST /tts       -> Processa texto e retorna JSON com audio_url
 * - GET  /audio/{filename} -> Serve arquivo de áudio (streaming)
 */
class TTSController extends Controller
{
    protected ElevenLabsService $elevenLabsService;

    /**
     * Injeta o serviço ElevenLabs via DI (Dependency Injection)
     */
    public function __construct(ElevenLabsService $elevenLabsService)
    {
        $this->elevenLabsService = $elevenLabsService;
    }

    /**
     * GET /
     *
     * Exibe página inicial com formulário de TTS
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('tts.index');
    }

    /**
     * POST /tts
     *
     * Processa texto e retorna URL do áudio gerado
     *
     * Fluxo:
     * 1. Valida request (text obrigatório, max 5000 chars)
     * 2. Chama ElevenLabsService->textToSpeech()
     * 3. Retorna JSON: { success: true, audio_url: "..." }
     *
     * Rate Limiting: aplicado via middleware throttle:10,1 (10 req/min)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        // Validação de entrada
        // Regras:
        // - text: obrigatório, string, min 1 char, max 5000 chars (limite da API)
        // - voice_id: opcional, string (se não fornecido, usa padrão do .env)
        $validator = Validator::make($request->all(), [
            'text' => [
                'required',
                'string',
                'min:1',
                'max:5000',
            ],
            'voice_id' => [
                'nullable',
                'string',
                'max:100',
            ],
        ], [
            // Mensagens customizadas em português
            'text.required' => 'O campo texto é obrigatório.',
            'text.min' => 'O texto deve ter pelo menos 1 caractere.',
            'text.max' => 'O texto não pode exceder 5000 caracteres.',
            'voice_id.max' => 'Voice ID inválido.',
        ]);

        // Se validação falhar, retorna erros 422
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $text = $validated['text'];
        $voiceId = $validated['voice_id'] ?? null;

        try {
            // Chama serviço ElevenLabs
            // Isso faz o request HTTP, salva o arquivo e retorna a URL pública
            $audioUrl = $this->elevenLabsService->textToSpeech($text, $voiceId);

            // Log de sucesso (útil para analytics/auditoria)
            Log::info('TTS Generated Successfully', [
                'text_length' => strlen($text),
                'voice_id' => $voiceId,
                'audio_url' => $audioUrl,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Retorna JSON de sucesso
            return response()->json([
                'success' => true,
                'message' => 'Áudio gerado com sucesso!',
                'audio_url' => $audioUrl,
                'text_length' => strlen($text),
            ], 200);

        } catch (ElevenLabsException $e) {
            // Erro específico da API ElevenLabs
            // (autenticação, rate limit, voz não encontrada, etc.)
            Log::error('ElevenLabs Error', [
                'message' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'response_data' => $e->getResponseData(),
                'text_length' => strlen($text),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStatusCode(),
            ], $e->getStatusCode() >= 500 ? 500 : 400);

        } catch (\Exception $e) {
            // Erro inesperado (disco cheio, falha de rede, etc.)
            Log::error('Unexpected TTS Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor. Tente novamente.',
            ], 500);
        }
    }

    /**
     * GET /voices
     *
     * Retorna lista de vozes disponíveis (opcional, para UI avançada)
     *
     * @return JsonResponse
     */
    public function getVoices(): JsonResponse
    {
        try {
            $voices = $this->elevenLabsService->getAvailableVoices();

            return response()->json([
                'success' => true,
                'voices' => $voices,
            ], 200);

        } catch (ElevenLabsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

