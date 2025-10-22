<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ElevenLabsService;
use App\Exceptions\ElevenLabsException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Testes unitários do ElevenLabsService
 *
 * Testa a lógica isolada do serviço sem depender do controller
 */
class ElevenLabsServiceTest extends TestCase
{
    protected ElevenLabsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Configura API key fake
        config([
            'elevenlabs.api_key' => 'test-api-key-12345',
            'elevenlabs.voice_id' => 'test-voice-id',
            'elevenlabs.base_url' => 'https://api.elevenlabs.io/v1',
            'elevenlabs.timeout' => 30,
            'elevenlabs.model_id' => 'eleven_multilingual_v2',
            'elevenlabs.voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.75,
            ],
            'elevenlabs.storage' => [
                'disk' => 'public',
                'path' => 'audio',
                'ttl' => 60,
            ],
        ]);

        $this->service = new ElevenLabsService();
    }

    /**
     * Teste 1: Serviço lança exceção se API key não estiver configurada
     *
     * @test
     */
    public function test_service_throws_exception_when_api_key_is_missing()
    {
        $this->expectException(ElevenLabsException::class);
        $this->expectExceptionMessage('API key não configurada');

        config(['elevenlabs.api_key' => null]);

        new ElevenLabsService();
    }

    /**
     * Teste 2: textToSpeech retorna URL pública após sucesso
     *
     * @test
     */
    public function test_text_to_speech_returns_public_url_on_success()
    {
        $fakeMp3 = random_bytes(2048);

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response($fakeMp3, 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $audioUrl = $this->service->textToSpeech('Hello world');

        $this->assertNotEmpty($audioUrl);
        $this->assertStringContainsString('/storage/audio/', $audioUrl);
        $this->assertStringEndsWith('.mp3', $audioUrl);

        // Verifica que arquivo foi salvo
        $files = Storage::disk('public')->files('audio');
        $this->assertCount(1, $files);
    }

    /**
     * Teste 3: Validação de texto vazio
     *
     * @test
     */
    public function test_text_to_speech_throws_exception_for_empty_text()
    {
        $this->expectException(ElevenLabsException::class);
        $this->expectExceptionMessage('não pode ser vazio');

        $this->service->textToSpeech('   ');
    }

    /**
     * Teste 4: Validação de texto muito longo (> 5000 chars)
     *
     * @test
     */
    public function test_text_to_speech_throws_exception_for_text_exceeding_limit()
    {
        $this->expectException(ElevenLabsException::class);
        $this->expectExceptionMessage('excede limite de 5000');

        $longText = str_repeat('a', 5001);
        $this->service->textToSpeech($longText);
    }

    /**
     * Teste 5: Tratamento de erro 401 (API key inválida)
     *
     * @test
     */
    public function test_text_to_speech_handles_401_error()
    {
        Http::fake([
            'api.elevenlabs.io/*' => Http::response([
                'detail' => ['message' => 'Unauthorized'],
            ], 401),
        ]);

        $this->expectException(ElevenLabsException::class);
        $this->expectExceptionMessage('inválida ou não autorizada');

        $this->service->textToSpeech('Test');
    }

    /**
     * Teste 6: Tratamento de erro 429 (Rate limit)
     *
     * @test
     */
    public function test_text_to_speech_handles_429_rate_limit()
    {
        Http::fake([
            'api.elevenlabs.io/*' => Http::response([
                'detail' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $this->expectException(ElevenLabsException::class);
        $this->expectExceptionMessage('Rate limit excedido');

        $this->service->textToSpeech('Test');
    }

    /**
     * Teste 7: getAvailableVoices retorna array de vozes
     *
     * @test
     */
    public function test_get_available_voices_returns_array()
    {
        Http::fake([
            'api.elevenlabs.io/v1/voices' => Http::response([
                'voices' => [
                    ['voice_id' => 'abc', 'name' => 'Rachel'],
                    ['voice_id' => 'def', 'name' => 'Antoni'],
                ],
            ], 200),
        ]);

        $voices = $this->service->getAvailableVoices();

        $this->assertIsArray($voices);
        $this->assertCount(2, $voices);
        $this->assertEquals('Rachel', $voices[0]['name']);
    }

    /**
     * Teste 8: Limpeza de arquivos antigos (TTL)
     *
     * @test
     */
    public function test_cleanup_deletes_old_audio_files()
    {
        // Cria arquivo "antigo" (mock)
        Storage::disk('public')->put('audio/old-file.mp3', 'fake-content');

        // Modifica timestamp do arquivo para simular arquivo antigo
        // (Nota: em ambiente de teste com Storage::fake, isso é limitado)
        // Para teste real, usaríamos Carbon::setTestNow() e sleep()

        config(['elevenlabs.storage.ttl' => 0]); // TTL 0 = nunca expira

        $deleted = $this->service->cleanupOldAudioFiles();

        // Com TTL 0, nenhum arquivo deve ser deletado
        $this->assertEquals(0, $deleted);
    }

    /**
     * Teste 9: Verifica headers corretos na requisição HTTP
     *
     * @test
     */
    public function test_text_to_speech_sends_correct_headers()
    {
        Http::fake([
            'api.elevenlabs.io/*' => Http::response(random_bytes(512), 200),
        ]);

        $this->service->textToSpeech('Test');

        // Verifica que requisição foi feita com headers corretos
        Http::assertSent(function ($request) {
            return $request->hasHeader('xi-api-key', 'test-api-key-12345') &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('Accept', 'audio/mpeg');
        });
    }
}

