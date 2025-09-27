<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

use Mcp\Server\Authentication\Dto\OAuthErrorResponse;

/**
 * Base class for OAuth errors.
 */
abstract class OAuthError extends \Exception
{
    public function __construct(
        protected readonly string $errorCode,
        string $message = '',
        protected readonly ?string $errorUri = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorUri(): ?string
    {
        return $this->errorUri;
    }

    public function toResponseObject(): OAuthErrorResponse
    {
        return new OAuthErrorResponse(
            $this->errorCode,
            $this->getMessage() ?: null,
            $this->errorUri,
        );
    }
}
