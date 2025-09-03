<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\Enums;

enum BackedStringEnum: string
{
    case OptionA = 'A';
    case OptionB = 'B';
}
