<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Provider;

use Mcp\Server\Authentication\AuthInfo;
use Mcp\Server\Authentication\Contract\UserProviderInterface;
use Mcp\Server\Authentication\Dto\UserProfile;

final readonly class InMemoryUserProvider implements UserProviderInterface
{
    public function __construct(
        private ?AuthInfo $authInfo = null,
    ) {}

    public function getUser(): ?UserProfile
    {
        return $this->authInfo?->getUserProfile();
    }
}
