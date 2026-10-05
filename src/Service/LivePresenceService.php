<?php

namespace Wexample\SymfonyLive\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Whether anyone is listening on a topic, asked of the hub.
 *
 * What lets the server do on its own what a page used to ask for by polling:
 * the browser holding a stream open on a topic is already the sign that
 * someone is looking, and the hub keeps the list — its subscriptions API,
 * enabled by the `subscriptions` directive of the Caddyfile this package
 * installs.
 */
class LivePresenceService
{
    private const int TIMEOUT_SECONDS = 2;

    private readonly LcobucciFactory $tokenFactory;

    public function __construct(
        private readonly HubInterface $hub,
        private readonly HttpClientInterface $httpClient,
        string $jwtSecret,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->tokenFactory = new LcobucciFactory($jwtSecret);
    }

    /**
     * False as well when the hub cannot say: a hub down holds no stream, so
     * nobody is hearing anything anyway.
     */
    public function hasSubscribers(string $topic): bool
    {
        return in_array($topic, $this->subscribedTopics($topic), true);
    }

    /**
     * Every topic someone is listening on, in one request: what a task going
     * over many entities asks once, rather than once per entity.
     *
     * @param string|null $topic only that one, for a hub holding many
     *
     * @return string[]
     */
    public function subscribedTopics(?string $topic = null): array
    {
        try {
            $subscriptions = $this->httpClient->request(
                'GET',
                $this->hub->getUrl().'/subscriptions'.(null === $topic ? '' : '/'.rawurlencode($topic)),
                [
                    // The subscriptions API answers subscribers allowed on what it
                    // lists. This token never leaves the server.
                    'auth_bearer' => $this->tokenFactory->create(subscribe: ['*'], publish: null),
                    'timeout' => self::TIMEOUT_SECONDS,
                ]
            )->toArray()['subscriptions'] ?? [];
        } catch (HttpClientExceptionInterface $exception) {
            $this->logger?->warning(
                'Live presence unknown, the hub did not answer: {message}',
                ['message' => $exception->getMessage(), 'topic' => $topic]
            );

            return [];
        }

        $topics = [];

        foreach ($subscriptions as $subscription) {
            if ($subscription['active'] ?? false) {
                $topics[$subscription['topic']] = true;
            }
        }

        return array_keys($topics);
    }
}
