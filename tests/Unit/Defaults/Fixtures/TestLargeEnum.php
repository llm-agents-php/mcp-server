<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Defaults\Fixtures;

enum TestLargeEnum: string
{
    case VALUE_001 = 'value_001';
    case VALUE_002 = 'value_002';
    case VALUE_003 = 'value_003';
    case VALUE_004 = 'value_004';
    case VALUE_005 = 'value_005';
    case PREFIX_A = 'prefix_a';
    case PREFIX_B = 'prefix_b';
    case PREFIX_C = 'prefix_c';
    case DIFFERENT_001 = 'different_001';
    case DIFFERENT_002 = 'different_002';
}
