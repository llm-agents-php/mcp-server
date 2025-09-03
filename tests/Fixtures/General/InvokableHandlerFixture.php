<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\General;

class InvokableHandlerFixture
{
    public array $argsReceived;

    public function __construct(public string $type = "default") {}

    public function __invoke(string $arg1, int $arg2 = 0): array
    {
        $this->argsReceived = \func_get_args();
        return ['invoked' => $this->type, 'arg1' => $arg1, 'arg2' => $arg2];
    }
}
