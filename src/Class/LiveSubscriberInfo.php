<?php

namespace Wexample\SymfonyLive\Class;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * What a subscriber needs to open its stream. The array form is the wire contract read by
 * the browser driver in js-api MercureLiveUpdatesDriver, so its keys are camelCase and
 * fixed.
 */
class LiveSubscriberInfo
{
    /**
     * @param string[] $topics
     */
    public function __construct(
        public readonly string $hubUrl,
        public readonly string $jwt,
        public readonly array $topics,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'hubUrl' => $this->hubUrl,
            'jwt' => $this->jwt,
            'topics' => $this->topics,
            'expiresAt' => $this->expiresAt->format(DateTimeInterface::ATOM),
        ];
    }
}
