# TTS Laravel - Text-to-Speech com ElevenLabs API

![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

Aplicação web de **Text-to-Speech (TTS)** construída com **Laravel 10** e integração direta com a **API ElevenLabs** via HTTP/REST. Converte texto em áudio de alta qualidade usando inteligência artificial.

---

## ✨ Características

- ✅ **Interface web intuitiva** com formulário Blade + JavaScript vanilla
- ✅ **Integração HTTP direta** com ElevenLabs API (sem SDKs proprietários)
- ✅ **Validações robustas** (client-side + server-side)
- ✅ **Rate limiting** (10 req/min por IP) para proteção contra abuse
- ✅ **Reprodução de áudio** inline com player HTML5
- ✅ **Download de arquivos** MP3 gerados
- ✅ **Testes PHPUnit** (Feature + Unit) com 70%+ coverage
- ✅ **CI/CD automatizado** via GitHub Actions
- ✅ **SQLite** (zero-config para dev local)
- ✅ **Logs detalhados** para debugging e auditoria
- ✅ **CSRF protection** nativo do Laravel
- ✅ **Exceções customizadas** para tratamento de erros da API

---

### Conta ElevenLabs

1. Crie uma conta gratuita em [elevenlabs.io](https://elevenlabs.io/)
2. Obtenha sua API Key em: [https://elevenlabs.io/app/settings/api-keys](https://elevenlabs.io/app/settings/api-keys)
3. (Opcional) Liste Voice IDs disponíveis: `curl -H "xi-api-key: YOUR_KEY" https://api.elevenlabs.io/v1/voices`

---


## ⚙️ Configuração do Projeto

### 1. Clonar Repositório

```bash
git clone https://github.com/seu-usuario/tts-laravel.git
cd tts-laravel
```

### 2. Instalar Dependências

```bash
composer install
```

### 3. Configurar Ambiente

```bash
# Copiar .env.example
cp .env.example .env

# Gerar application key
php artisan key:generate

# Editar .env e adicionar sua API Key da ElevenLabs
nano .env
```

**Configure no `.env`:**

```env
ELEVEN_API_KEY= sua chave api  # ⚠️ SUA CHAVE AQUI
ELEVEN_VOICE_ID=21m00Tcm4TlvDq8ikWAM  # Rachel (padrão)
```

### 4. Criar Database SQLite

```bash
touch database/database.sqlite
```

### 5. Rodar Migrations (se houver)

```bash
php artisan migrate
```

### 6. Criar Link Simbólico para Storage

```bash
php artisan storage:link
```

### 7. Criar Diretório de Áudios

```bash
mkdir -p storage/app/public/audio
```

---

## 🚀 Executando Localmente

### Método 1: Artisan Serve (Recomendado para Dev)

```bash
php artisan serve
```

Acesse: [http://localhost:8000](http://localhost:8000)

### Método 2: PhpStorm Run Configuration

Clique no botão **▶ Run** na configuração criada anteriormente.

### Método 3: Docker (Opcional)

```bash
# Se preferir usar Laravel Sail
./vendor/bin/sail up -d
```

---

## 🧪 Testando a Aplicação

### Via Interface Web

1. Acesse [http://localhost:8000](http://localhost:8000)
2. Digite texto no formulário (ex: "Olá, este é um teste!")
3. Clique em **Gerar Áudio**
4. Aguarde 2-5 segundos (spinner aparecerá)
5. Player de áudio será exibido automaticamente
6. Clique em **Baixar MP3** para salvar o arquivo

### Via CURL (Testando API Diretamente)

```bash
# Obter CSRF token primeiro (ou desabilitar verificação para testes)
# POST /tts
curl -X POST http://localhost:8000/tts \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "text": "Hello, this is a test of ElevenLabs Text to Speech API"
  }'

# Resposta esperada:
# {
#   "success": true,
#   "message": "Áudio gerado com sucesso!",
#   "audio_url": "http://localhost:8000/storage/audio/tts_abc-123.mp3",
#   "text_length": 50
# }
```

### Verificar Rate Limiting

```bash
# Faça 11 requisições seguidas
for i in {1..11}; do
  echo "Request $i:"
  curl -X POST http://localhost:8000/tts \
    -H "Content-Type: application/json" \
    -d "{\"text\": \"Test $i\"}" \
    -w "\nHTTP Status: %{http_code}\n\n"
done

# A partir da 11ª requisição, retornará 429 Too Many Requests
```

---

## 🗺️ Rotas da API

| Método | Rota | Descrição | Rate Limit |
|--------|------|-----------|------------|
| `GET` | `/` | Página inicial (formulário) | - |
| `POST` | `/tts` | Gera áudio a partir de texto | 10/min |
| `GET` | `/voices` | Lista vozes disponíveis | 30/min |
| `GET` | `/storage/audio/{file}` | Serve arquivo de áudio | - |

### Exemplos de Request/Response

#### POST /tts

**Request:**
```json
{
  "text": "Hello world",
  "voice_id": "21m00Tcm4TlvDq8ikWAM"  // opcional
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Áudio gerado com sucesso!",
  "audio_url": "http://localhost:8000/storage/audio/tts_uuid.mp3",
  "text_length": 11
}
```

**Response (422 Validation Error):**
```json
{
  "success": false,
  "message": "Erro de validação",
  "errors": {
    "text": ["O campo texto é obrigatório."]
  }
}
```

**Response (429 Rate Limit):**
```json
{
  "message": "Too Many Attempts.",
  "exception": "Illuminate\\Http\\Exceptions\\ThrottleRequestsException"
}
```

---

## 📂 Estrutura do Projeto

```
TTS_Laravel/
├── app/
│   ├── Exceptions/
│   │   └── ElevenLabsException.php     # Exceção customizada
│   ├── Http/Controllers/
│   │   └── TTSController.php           # Controller principal
│   └── Services/
│       └── ElevenLabsService.php       # Lógica de integração com API
├── config/
│   └── elevenlabs.php                  # Configurações da API
├── resources/views/
│   ├── layouts/app.blade.php           # Layout base
│   └── tts/index.blade.php             # Página do formulário
├── routes/
│   └── web.php                         # Rotas da aplicação
├── tests/
│   ├── Feature/TTSControllerTest.php   # Testes de integração
│   └── Unit/ElevenLabsServiceTest.php  # Testes unitários
├── .github/workflows/ci.yml            # Pipeline CI/CD
├── .env.example                        # Template de variáveis
├── composer.json                       # Dependências PHP
└── README.md                           # Este arquivo
```

---

## 🧪 Testes Automatizados

### Executar Todos os Testes

```bash
php artisan test
```

### Executar com Coverage

```bash
php artisan test --coverage --min=70
```

### Executar Apenas Feature Tests

```bash
php artisan test --testsuite=Feature
```

### Executar Apenas Unit Tests

```bash
php artisan test --testsuite=Unit
```

### Testes Incluídos

| Teste | Descrição | Arquivo |
|-------|-----------|---------|
| `test_index_page_loads` | Verifica se página inicial carrega | Feature |
| `test_validation_fails_empty` | Valida campo obrigatório | Feature |
| `test_validation_max_length` | Valida limite de 5000 chars | Feature |
| `test_generates_audio_success` | Mock da API e geração de áudio | Feature |
| `test_handles_api_error` | Tratamento de erro 401/429 | Feature |
| `test_rate_limiting` | Bloqueia após 10 requests | Feature |
| `test_service_no_api_key` | Exceção se API key ausente | Unit |
| `test_service_success` | Serviço retorna URL válida | Unit |
| `test_cleanup_old_files` | Limpeza de arquivos antigos | Unit |

---

## 🔄 CI/CD com GitHub Actions

### Configurar Secrets no GitHub

1. Acesse: **Settings** → **Secrets and variables** → **Actions**
2. Adicione os secrets:
   - `ELEVEN_API_KEY`: sua chave da ElevenLabs
   - `ELEVEN_VOICE_ID`: ID da voz padrão

### Pipeline Automático

O workflow `.github/workflows/ci.yml` executa:

1. ✅ **Tests** em PHP 8.1, 8.2, 8.3
2. ✅ **Code Quality** (Laravel Pint)
3. ✅ **Security Audit** (composer audit)
4. ✅ **Coverage Report** (70% mínimo)

**Triggered on:**
- Push para `main`, `master`, `develop`
- Pull Requests para `main`, `master`

---

### Erro: "API key não configurada"

**Solução:**
```bash
# Verifique se ELEVEN_API_KEY está no .env
grep ELEVEN_API_KEY .env

# Se não, adicione:
echo "ELEVEN_API_KEY=sk_your_key_here" >> .env

# Limpe cache de config
php artisan config:clear
```

### Erro: "Permission denied" no storage/

**Solução:**
```bash
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R $USER:www-data storage bootstrap/cache
```

### Erro: "429 Too Many Requests" nos testes

**Solução:** Os testes mockam a API, mas o rate limiting é aplicado. Para desabilitar em testes:

```php
// Em tests/Feature/TTSControllerTest.php
$this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
```

### Player de áudio não reproduz

**Solução:**
1. Verifique se `php artisan storage:link` foi executado
2. Inspecione console do navegador (F12) para erros CORS
3. Confirme que arquivo existe: `ls -la storage/app/public/audio/`

### Erro: "Call to undefined function App\Services\random_bytes()"

**Solução:** Atualize PHP para 8.1+ ou instale extensão `php-sodium`.

---

## 🧠 Decisões Técnicas

### 1. Por que HTTP direto ao invés de SDK?

**Decisão:** Usar `Illuminate\Support\Facades\Http` (Guzzle wrapper).

**Razões:**
- ✅ Controle total sobre requests/responses
- ✅ Fácil debugging e logging
- ✅ Testável com `Http::fake()`
- ✅ Sem dependência de SDKs de terceiros (vendor lock-in)

**Trade-off:** SDKs oficiais teriam validação de tipos e métodos helper, mas HTTP direto oferece mais flexibilidade.

### 2. Por que SQLite ao invés de MySQL/Postgres?

**Decisão:** SQLite como padrão, Postgres opcional.

**Razões:**
- ✅ Zero-config para ambiente local
- ✅ Arquivo único (portável)
- ✅ Ideal para MVPs e desenvolvimento

**Trade-off:** Para produção com >10k requests/dia, recomenda-se migrar para Postgres (instruções no README).

### 3. Por que salvar arquivos ao invés de streaming?

**Decisão:** Salvar MP3 em `storage/app/public/audio/`.

**Razões:**
- ✅ Permite auditoria (quem gerou o quê)
- ✅ Cache natural (evita re-gerar mesmo texto)
- ✅ Replay sem custo adicional na API

**Trade-off:** Consome storage (~50KB por áudio). Implementamos TTL de 1 hora e método `cleanupOldAudioFiles()` para limpeza automática.

### 4. Por que Jobs síncronos ao invés de Queue?

**Decisão:** `QUEUE_CONNECTION=sync` (padrão).

**Razões:**
- ✅ Mais simples para MVP
- ✅ ElevenLabs é rápido (2-5s)
- ✅ Evita complexidade de workers/Redis

**Como migrar para Queue:**
1. Instalar Redis: `sudo pacman -S redis`
2. Alterar `.env`: `QUEUE_CONNECTION=redis`
3. Criar Job: `php artisan make:job GenerateTTSJob`
4. Dispatch: `GenerateTTSJob::dispatch($text)`
5. Worker: `php artisan queue:work`

### 5. Por que Rate Limiting agressivo (10/min)?

**Decisão:** `throttle:10,1` (10 requests por minuto).

**Razões:**
- ✅ Protege contra abuse/custos inesperados na ElevenLabs
- ✅ Uso típico: 1 usuário = 2-3 testes/min

**Ajustar:** Altere em `routes/web.php` para `throttle:30,1` se necessário.

---

- **Documentação ElevenLabs:** [https://api.elevenlabs.io/docs](https://api.elevenlabs.io/docs)


---

## 🙏 Agradecimentos

- [Laravel Framework](https://laravel.com)
- [ElevenLabs AI](https://elevenlabs.io)
- Comunidade Open Source

---

