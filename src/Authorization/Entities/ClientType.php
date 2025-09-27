<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Entities;

/**
 * OAuth 2.1 client types
 */
enum ClientType: string
{
    case CONFIDENTIAL = 'confidential';
    case PUBLIC = 'public';
}
