<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Defaults\Fixtures;

enum TestStringEnum: string
{
    case FIRST = 'first_value';
    case SECOND = 'second_value';
    case ANOTHER = 'another_value';
    case SIMILAR = 'similar_value';
}
