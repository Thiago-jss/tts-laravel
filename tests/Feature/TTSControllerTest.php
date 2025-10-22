<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Testes de integração do TTSController
 *
 * Testa os endpoints:
 * - GET / (exibe formulário)
 * - POST /tts (gera áudio)
 * - Rate limiting
 */
class TTSControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configura storage fake para não criar arquivos reais
        Storage::fake('public');

        // Define API key fake para testes
        config(['elevenlabs.api_key' => 'test-api-key']);
    }

    /**
     * Teste 1: Verifica se a página inicial carrega corretamente
     *
     * @test
     */
    public function test_index_page_loads_successfully()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('tts.index');
        $response->assertSee('Text-to-Speech');
        $response->assertSee('Digite ou cole seu texto');
    }

    /**
     * Teste 2: Validação de campo obrigatório
     *
     * Verifica que o endpoint /tts retorna erro 422 quando
     * o campo 'text' está vazio
     *
     * @test
     */
    public function test_tts_validation_fails_when_text_is_empty()
    {
        $response = $this->postJson('/tts', [
            'text' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Erro de validação',
        ]);
        $response->assertJsonValidationErrors(['text']);
    }

    /**
     * Teste 3: Validação de tamanho máximo do texto
     *
     * @test
     */
    public function test_tts_validation_fails_when_text_exceeds_max_length()
    {
        $longText = str_repeat('a', 5001); // 5001 caracteres (limite é 5000)

        $response = $this->postJson('/tts', [
            'text' => $longText,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['text']);
    }

    /**
     * Teste 4: Geração de áudio bem-sucedida (com mock da API)
     *
     * Simula resposta da ElevenLabs API e verifica que:
     * - Controller retorna status 200
     * - JSON contém audio_url
     * - Arquivo é salvo no storage
     *
     * @test
     */
    public function test_tts_generates_audio_successfully_with_mocked_api()
    {
        // Mock da resposta da API ElevenLabs
        // Simula um arquivo MP3 binário (bytes aleatórios para teste)
        $fakeMp3Content = random_bytes(1024); // 1KB de dados simulando MP3

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response($fakeMp3Content, 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $response = $this->postJson('/tts', [
            'text' => 'Hello, this is a test.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'audio_url',
            'text_length',
        ]);

        // Verifica que arquivo foi criado no storage
        $audioUrl = $response->json('audio_url');
        $this->assertNotEmpty($audioUrl);

        // Verifica que o storage contém pelo menos um arquivo na pasta audio/
        $files = Storage::disk('public')->files('audio');
        $this->assertCount(1, $files);
    }

    /**
     * Teste 5: Tratamento de erro da API ElevenLabs (401 Unauthorized)
     *
     * @test
     */
    public function test_tts_handles_elevenlabs_api_error_gracefully()
    {
        // Mock de erro 401 (API key inválida)
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response([
                'detail' => [
                    'status' => 'invalid_api_key',
                    'message' => 'Invalid API key',
                ],
            ], 401),
        ]);

        $response = $this->postJson('/tts', [
            'text' => 'Test text',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('inválida', $response->json('message'));
    }

    /**
     * Teste 6: Rate Limiting
     *
     * Verifica que após 10 requisições em 1 minuto,
     * o middleware throttle retorna 429 Too Many Requests
     *
     * @test
     */
    public function test_rate_limiting_blocks_excessive_requests()
    {
        // Mock da API
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(random_bytes(512), 200),
        ]);

        // Faz 10 requisições (limite)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/tts', ['text' => "Test $i"]);
            $response->assertStatus(200);
        }

        // 11ª requisição deve ser bloqueada (429 Too Many Requests)
        $response = $this->postJson('/tts', ['text' => 'Test 11']);
        $response->assertStatus(429);
    }

    /**
     * Teste 7: Endpoint /voices retorna lista de vozes (mock)
     *
     * @test
     */
    public function test_voices_endpoint_returns_available_voices()
    {
        // Mock da resposta de vozes
        Http::fake([
            'api.elevenlabs.io/v1/voices' => Http::response([
                'voices' => [
                    [
                        'voice_id' => '21m00Tcm4TlvDq8ikWAM',
                        'name' => 'Rachel',
                        'category' => 'premade',
                    ],
                    [
                        'voice_id' => 'EXAVITQu4vr4xnSDxMaL',
                        'name' => 'Bella',
                        'category' => 'premade',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/voices');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonCount(2, 'voices');
    }

    /**
     * Teste 8: CSRF Protection
     *
     * Verifica que requisições JSON sem CSRF token são aceitas
     * (Laravel não aplica CSRF em API/JSON requests por padrão)
     * Mas forms Blade têm @csrf token
     *
     * @test
     */
    public function test_csrf_token_present_in_view()
    {
        // Verifica que a view contém CSRF token
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('csrf-token', false);
    }
}

