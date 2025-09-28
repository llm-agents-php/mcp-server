<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Contract;

use Mcp\Server\Authentication\Dto\UserProfile;

interface UserProviderInterface
{
    public function getUser(): ?UserProfile;
}
