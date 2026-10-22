<?php

namespace Wexample\SymfonyLive\Service;

use DateTimeImmutable;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Wexample\SymfonyLive\Class\LiveSubscriberInfo;

class LiveSubscriberTokenService
{
    private readonly LcobucciFactory $tokenFactory;

    public function __construct(
        string $jwtSecret,
        private readonly string $hubPublicUrl,
        private readonly int $ttlSeconds,
    ) {
        $this->tokenFactory = new LcobucciFactory($jwtSecret, jwtLifetime: $this->ttlSeconds);
    }

    /**
     * @param string[] $topics the exact topics the token grants, never a wildcard: the
     *                         token reaches the browser and is replayable until it expires
     */
    public function buildSubscriberInfo(array $topics): LiveSubscriberInfo
    {
        $topics = array_values($topics);

        return new LiveSubscriberInfo(
            hubUrl: $this->hubPublicUrl,
            // A null publish claim omits it entirely, where an empty array would grant
            // publication on the empty topic selector.
            jwt: $this->tokenFactory->create(subscribe: $topics, publish: null),
            topics: $topics,
            expiresAt: new DateTimeImmutable('+'.$this->ttlSeconds.' seconds'),
        );
    }
}
