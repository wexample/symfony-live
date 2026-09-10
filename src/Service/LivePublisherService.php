<?php

namespace Wexample\SymfonyLive\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class LivePublisherService
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    /**
     * Sends the `{event, data}` envelope every browser subscriber expects, so a topic
     * carries a named event rather than a bare payload the receiver has to guess.
     *
     * @param string|string[] $topics
     *
     * @return string the update id returned by the hub
     */
    public function publishEvent(
        string|array $topics,
        string $event,
        mixed $data
    ): string {
        return $this->publish($topics, [
            'event' => $event,
            'data' => $data,
        ]);
    }

    /**
     * @param string|string[] $topics
     *
     * @return string the update id returned by the hub
     */
    public function publish(
        string|array $topics,
        array $payload
    ): string {
        return $this->hub->publish(
            new Update(
                $topics,
                json_encode($payload, JSON_THROW_ON_ERROR)
            )
        );
    }
}
