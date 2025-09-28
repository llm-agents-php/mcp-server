<?php

declare(strict_types=1);

namespace Mcp\Server\Exception;

final readonly class NullExceptionReporter implements ExceptionReporterInterface
{
    public function report(\Throwable $exception): void
    {
        // Do nothing
    }
}
