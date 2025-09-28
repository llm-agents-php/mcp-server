<?php

declare(strict_types=1);

namespace Mcp\Server\Exception;

interface ExceptionReporterInterface
{
    public function report(\Throwable $exception): void;
}
