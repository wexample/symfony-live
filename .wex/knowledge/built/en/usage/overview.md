`symfony-live` is the server half of Wex live updates: it publishes to a Mercure hub, hands
subscribers a token scoped to the topics they asked for, and owns the topic grammar and the
payload envelope both halves have to agree on. The browser half stays where it is —
`LiveUpdatesService` in `symfony-loader`, `MercureLiveUpdatesDriver` in `js-api` — and this
package never depends on it.

## What the two halves agree on

Three things cross the wire, and all three are written here.

A **topic** is `entity/<kebab-entity-name>/<action>/<identifier>`, built by
`LiveTopicHelper::entity()`. The browser rebuilds the same string segment by segment through
`renderNode.liveUpdatesTopic()`, so a change to the grammar is a change on both sides.
`LiveTopicHelper::join()` prefixes a topic when an API version has to scope it.

An **update payload** is `{"event": "...", "data": ...}`, produced by
`LivePublisherService::publishEvent()`. A subscriber reads the event name to decide what to
do, instead of guessing from the shape of the data.

**Subscriber info** is `{hubUrl, jwt, topics, expiresAt}`, produced by
`LiveSubscriberTokenService::buildSubscriberInfo()` and consumed by the browser driver as its
Mercure configuration. Keys are camelCase and fixed.

## Publishing

```php
use Wexample\SymfonyLive\Enum\LiveTopicAction;
use Wexample\SymfonyLive\Helper\LiveTopicHelper;
use Wexample\SymfonyLive\Service\LivePublisherService;

public function __construct(
    private readonly LivePublisherService $publisher,
) {
}

$this->publisher->publishEvent(
    LiveTopicHelper::entity($session, LiveTopicAction::EVENT, $session->getSecureId()),
    'message.save',
    $this->messageNormalizer->normalize($message)
);
```

`publish()` is there for a payload that is not an event envelope. Both return the update id
the hub answers with.

## Handing a subscriber its token

The token reaches the browser and is replayable until it expires, so it grants the exact
topics asked for and carries no publish claim at all. Build it in the controller that already
knows the caller may see the entity:

```php
public function subscribeInfo(
    Session $session,
    LiveSubscriberTokenService $tokens,
): ApiResponse {
    $this->denyAccessUnlessGranted(AbstractEntityVoter::VIEW, $session);

    return self::apiResponseSuccess(
        data: $tokens->buildSubscriberInfo([
            LiveTopicHelper::entity($session, LiveTopicAction::EVENT, $session->getSecureId()),
        ])->toArray()
    );
}
```

## Configuration

The bundle prepends a `default` Mercure hub, so an app that ships the env vars needs no
`config/packages/mercure.yaml` of its own:

- `MERCURE_URL` — where Symfony publishes, reachable from the containers
- `MERCURE_PUBLIC_URL` — where the browser subscribes, handed out in `subscribeInfo`
- `MERCURE_JWT_SECRET` — signs both the publisher token and the subscriber ones

Everything else has a default and is only worth setting to change the token lifetime:

```yaml
# config/packages/wexample_symfony_live.yaml
wexample_symfony_live:
    hub_public_url: '%env(MERCURE_PUBLIC_URL)%'
    jwt_secret: '%env(MERCURE_JWT_SECRET)%'
    subscriber_token_ttl: 3600
```
