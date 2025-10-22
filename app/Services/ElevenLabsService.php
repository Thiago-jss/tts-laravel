<?php

namespace App\Services;

use App\Exceptions\ElevenLabsException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Serviço de integração com a API ElevenLabs para Text-to-Speech
 *
 * Este service é responsável por:
 * - Fazer requests HTTP à API ElevenLabs
 * - Tratar responses (binário de áudio)
 * - Salvar arquivos MP3 no storage
 * - Gerenciar erros e timeouts
 *
 * Documentação da API: https://api.elevenlabs.io/docs
 *
 * Exemplo de uso:
 * $service = new ElevenLabsService();
 * $audioUrl = $service->textToSpeech('Hello world', 'voice-id-optional');
 */
class ElevenLabsService
{
    protected ?string $apiKey;
    protected string $baseUrl;
    protected int $timeout;
    protected string $defaultVoiceId;
    protected string $modelId;
    protected array $voiceSettings;

    /**
     * Inicializa o serviço com configurações do .env
     */
    public function __construct()
    {
        $apiKey = config('elevenlabs.api_key');

        // Validação: API key é obrigatória
        if (empty($apiKey)) {
            throw new ElevenLabsException(
                'ElevenLabs API key não configurada. Defina ELEVEN_API_KEY no .env',
                500
            );
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = config('elevenlabs.base_url');
        $this->timeout = config('elevenlabs.timeout');
        $this->defaultVoiceId = config('elevenlabs.voice_id');
        $this->modelId = config('elevenlabs.model_id');
        $this->voiceSettings = config('elevenlabs.voice_settings');
    }

    /**
     * Converte texto em áudio usando a API ElevenLabs
     *
     * Fluxo:
     * 1. Valida parâmetros
     * 2. Monta payload JSON
     * 3. Faz POST para /v1/text-to-speech/{voice_id}
     * 4. Recebe binário MP3
     * 5. Salva em storage/app/public/audio/
     * 6. Retorna URL público do arquivo
     *
     * @param string $text Texto a ser convertido (max 5000 caracteres)
     * @param string|null $voiceId ID da voz (opcional, usa padrão se null)
     * @return string URL público do arquivo de áudio gerado
     * @throws ElevenLabsException
     */
    public function textToSpeech(string $text, ?string $voiceId = null): string
    {
        // Usa voz padrão se não especificada
        $voiceId = $voiceId ?? $this->defaultVoiceId;

        // Valida tamanho do texto (limite da API)
        if (strlen($text) > 5000) {
            throw new ElevenLabsException(
                'Texto excede limite de 5000 caracteres',
                400
            );
        }

        if (empty(trim($text))) {
            throw new ElevenLabsException(
                'Texto não pode ser vazio',
                400
            );
        }

        // Monta URL do endpoint
        $url = "{$this->baseUrl}/text-to-speech/{$voiceId}";

        // Payload da requisição
        $payload = [
            'text' => $text,
            'model_id' => $this->modelId,
            'voice_settings' => $this->voiceSettings,
        ];

        Log::info('ElevenLabs TTS Request', [
            'url' => $url,
            'voice_id' => $voiceId,
            'text_length' => strlen($text),
            'model' => $this->modelId,
        ]);

        try {
            // Faz requisição HTTP POST
            // Headers obrigatórios:
            // - xi-api-key: autenticação
            // - Content-Type: application/json
            // - Accept: audio/mpeg (importante para receber binário)
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'xi-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'audio/mpeg',
                ])
                ->post($url, $payload);

            // Tratamento de erros HTTP
            if ($response->failed()) {
                $statusCode = $response->status();
                $errorBody = $response->json();

                // Mensagens específicas por código de erro
                $errorMessage = match ($statusCode) {
                    401 => 'API Key inválida ou não autorizada',
                    404 => 'Voice ID não encontrado',
                    422 => 'Parâmetros inválidos: ' . ($errorBody['detail']['message'] ?? 'erro desconhecido'),
                    429 => 'Rate limit excedido. Tente novamente em alguns segundos',
                    500, 502, 503 => 'Erro interno da API ElevenLabs. Tente novamente',
                    default => 'Erro na API ElevenLabs',
                };

                Log::error('ElevenLabs API Error', [
                    'status' => $statusCode,
                    'body' => $errorBody,
                ]);

                throw new ElevenLabsException(
                    $errorMessage,
                    $statusCode,
                    $errorBody
                );
            }

            // Obtém binário do áudio (MP3)
            $audioContent = $response->body();

            // Valida que recebemos conteúdo
            if (empty($audioContent)) {
                throw new ElevenLabsException(
                    'Resposta da API está vazia',
                    500
                );
            }

            // Gera nome único para o arquivo
            $filename = 'tts_' . Str::uuid() . '.mp3';
            $storagePath = config('elevenlabs.storage.path') . '/' . $filename;

            // Salva no storage (storage/app/public/audio/)
            Storage::disk(config('elevenlabs.storage.disk'))
                ->put($storagePath, $audioContent);

            // Gera URL público
            $publicUrl = Storage::disk(config('elevenlabs.storage.disk'))
                ->url($storagePath);

            Log::info('ElevenLabs TTS Success', [
                'filename' => $filename,
                'size_bytes' => strlen($audioContent),
                'url' => $publicUrl,
            ]);

            return $publicUrl;

        } catch (ElevenLabsException $e) {
            // Re-lança exceções customizadas
            throw $e;
        } catch (\Exception $e) {
            // Captura erros de conexão, timeout, etc.
            Log::error('ElevenLabs Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ElevenLabsException(
                'Erro ao conectar com ElevenLabs API: ' . $e->getMessage(),
                500,
                null,
                $e
            );
        }
    }

    /**
     * Lista todas as vozes disponíveis na conta ElevenLabs
     *
     * Útil para permitir que usuário escolha a voz.
     * Endpoint: GET /v1/voices
     *
     * @return array Lista de vozes com id, name, category
     * @throws ElevenLabsException
     */
    public function getAvailableVoices(): array
    {
        $url = "{$this->baseUrl}/voices";

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'xi-api-key' => $this->apiKey,
                ])
                ->get($url);

            if ($response->failed()) {
                throw new ElevenLabsException(
                    'Erro ao buscar vozes disponíveis',
                    $response->status(),
                    $response->json()
                );
            }

            return $response->json('voices') ?? [];

        } catch (ElevenLabsException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ElevenLabsException(
                'Erro ao conectar com ElevenLabs API: ' . $e->getMessage(),
                500,
                null,
                $e
            );
        }
    }

    /**
     * Limpa arquivos de áudio antigos baseado no TTL configurado
     *
     * Pode ser chamado via cron/scheduler para economizar espaço.
     *
     * @return int Número de arquivos deletados
     */
    public function cleanupOldAudioFiles(): int
    {
        $ttl = config('elevenlabs.storage.ttl');

        if ($ttl === 0) {
            return 0; // TTL 0 = nunca expira
        }

        $disk = Storage::disk(config('elevenlabs.storage.disk'));
        $audioPath = config('elevenlabs.storage.path');
        $files = $disk->files($audioPath);
        $deleted = 0;
        $threshold = now()->subMinutes($ttl)->timestamp;

        foreach ($files as $file) {
            if ($disk->lastModified($file) < $threshold) {
                $disk->delete($file);
                $deleted++;
            }
        }

        Log::info("Cleaned up {$deleted} old audio files");

        return $deleted;
    }
}

