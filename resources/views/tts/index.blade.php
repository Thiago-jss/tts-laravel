@extends('layouts.app')

@section('title', 'Text-to-Speech - ElevenLabs')

@section('content')
<div class="container">
    <div class="card">
        <h1>🎙️ Text-to-Speech</h1>
        <p class="subtitle">Converta texto em áudio de alta qualidade usando ElevenLabs AI</p>

        <!-- Alert de erro -->
        <div id="alert-error" class="alert alert-error"></div>

        <!-- Alert de sucesso -->
        <div id="alert-success" class="alert alert-success"></div>

        <!-- Formulário -->
        <form id="tts-form">
            @csrf

            <div class="form-group">
                <label for="text">Digite ou cole seu texto:</label>
                <textarea
                    id="text"
                    name="text"
                    placeholder="Ex: Olá! Este é um teste de conversão de texto para áudio usando inteligência artificial da ElevenLabs."
                    maxlength="5000"
                    required
                ></textarea>
                <div class="char-counter">
                    <span id="char-count">0</span> / 5000 caracteres
                </div>
            </div>

            <button type="submit" id="submit-btn">
                <span class="button-text">🎵 Gerar Áudio</span>
                <div class="spinner"></div>
            </button>
        </form>

        <!-- Player de áudio (aparece após geração) -->
        <div id="audio-player" class="audio-player">
            <h3>✅ Áudio gerado com sucesso!</h3>
            <audio id="audio-element" controls></audio>
            <br>
            <a id="download-link" class="download-btn" download="tts-audio.mp3">
                ⬇️ Baixar MP3
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    /**
     * Script principal do TTS
     *
     * Fluxo:
     * 1. Usuário digita texto e clica "Gerar Áudio"
     * 2. Validação client-side (opcional, HTML já valida)
     * 3. POST /tts via fetch com CSRF token
     * 4. Mostra spinner durante request
     * 5. Recebe JSON com audio_url
     * 6. Cria <audio> element e reproduz
     * 7. Oferece botão de download
     */

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('tts-form');
        const textarea = document.getElementById('text');
        const charCount = document.getElementById('char-count');
        const submitBtn = document.getElementById('submit-btn');
        const audioPlayer = document.getElementById('audio-player');
        const audioElement = document.getElementById('audio-element');
        const downloadLink = document.getElementById('download-link');
        const alertError = document.getElementById('alert-error');
        const alertSuccess = document.getElementById('alert-success');

        // Contador de caracteres em tempo real
        textarea.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;

            // Muda cor se ultrapassar 4500 (warning)
            if (count > 4500) {
                charCount.parentElement.classList.add('warning');
            } else {
                charCount.parentElement.classList.remove('warning');
            }
        });

        // Submit do formulário
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Esconde alertas e player anteriores
            hideAlerts();
            audioPlayer.classList.remove('show');

            // Validação básica
            const text = textarea.value.trim();
            if (!text) {
                showError('Por favor, digite algum texto.');
                return;
            }

            if (text.length > 5000) {
                showError('Texto excede o limite de 5000 caracteres.');
                return;
            }

            // Mostra loading
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            try {
                // Faz request POST /tts
                const response = await fetch('{{ route('tts.generate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        text: text,
                        // voice_id: 'opcional' // Pode adicionar seletor de voz
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    // Erro retornado pela API
                    const errorMsg = data.message || 'Erro ao gerar áudio. Tente novamente.';

                    // Se for rate limit, mostra mensagem específica
                    if (response.status === 429) {
                        showError('⏱️ Muitas requisições. Aguarde 1 minuto e tente novamente.');
                    } else {
                        showError(errorMsg);
                    }
                    return;
                }

                // Sucesso! Mostra player
                showSuccess(data.message || 'Áudio gerado com sucesso!');

                // Define URL do áudio
                audioElement.src = data.audio_url;
                downloadLink.href = data.audio_url;

                // Mostra player
                audioPlayer.classList.add('show');

                // Auto-play (opcional, alguns navegadores bloqueiam)
                try {
                    audioElement.play();
                } catch (e) {
                    console.log('Auto-play bloqueado pelo navegador');
                }

            } catch (error) {
                console.error('Erro:', error);
                showError('❌ Erro de conexão. Verifique sua internet e tente novamente.');
            } finally {
                // Remove loading
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            }
        });

        // Funções auxiliares
        function showError(message) {
            alertError.textContent = message;
            alertError.classList.add('show');
            setTimeout(() => alertError.classList.remove('show'), 8000);
        }

        function showSuccess(message) {
            alertSuccess.textContent = message;
            alertSuccess.classList.add('show');
            setTimeout(() => alertSuccess.classList.remove('show'), 5000);
        }

        function hideAlerts() {
            alertError.classList.remove('show');
            alertSuccess.classList.remove('show');
        }
    });
</script>
@endpush

