<?php

namespace App\Exceptions;

use Exception;

/**
 * Exceção customizada para erros da API ElevenLabs
 *
 * Permite tratamento específico de erros relacionados à integração
 * com a ElevenLabs API (rate limit, autenticação, timeouts, etc.)
 */
class ElevenLabsException extends Exception
{
    protected int $statusCode;
    protected ?array $responseData;

    /**
     * Cria uma nova instância da exceção
     *
     * @param string $message Mensagem de erro
     * @param int $statusCode Código HTTP de resposta
     * @param array|null $responseData Dados da resposta da API
     * @param \Throwable|null $previous Exceção anterior
     */
    public function __construct(
        string $message = "ElevenLabs API Error",
        int $statusCode = 500,
        ?array $responseData = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->responseData = $responseData;
    }

    /**
     * Retorna o código de status HTTP
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Retorna os dados da resposta da API
     */
    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    /**
     * Converte a exceção para array (útil para logging)
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'status_code' => $this->statusCode,
            'response_data' => $this->responseData,
        ];
    }
}

