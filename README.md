# symfony-live

Version: 2.0.0

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

## Table of Contents

- [What the two halves agree on](#what-the-two-halves-agree-on)
- [Publishing](#publishing)
- [Handing a subscriber its token](#handing-a-subscriber-its-token)
- [Configuration](#configuration)
- [Where the cut is](#where-the-cut-is)
- [Layout](#layout)
- [Two tokens, not one](#two-tokens-not-one)
- [No dependency on the browser half](#no-dependency-on-the-browser-half)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Where the cut is

Live updates have two halves. The browser half already exists and works: `LiveUpdatesService`
in `symfony-loader` is an `AppService` of that registry, wired to `EventsService`,
`ConnectionStatusService` and the `RenderNode` mount hooks; `LiveUpdatesConnection` and
`MercureLiveUpdatesDriver` in `js-api` sit under it. None of that moves here — pulling it out
would make `symfony-live` depend on `symfony-loader` and `symfony-design-system`, which is the
layering upside down.

What this package takes instead is the server half **and the contract**: the topic grammar,
the update envelope and the subscriber-info payload. Those three were written twice before —
once in an app's message handlers, once in the browser driver — with nothing holding them
together. They now live in `Helper/LiveTopicHelper`, `Service/LivePublisherService` and
`Class/LiveSubscriberInfo`, and the browser is a consumer that already reads exactly those
keys.

## Layout

- `Helper/LiveTopicHelper` — the topic grammar, static, no state
- `Enum/LiveTopicAction` — the action vocabulary a topic segment may take
- `Class/LiveSubscriberInfo` — the wire shape handed to a subscriber
- `Service/LivePublisherService` — wraps `HubInterface`, owns the `{event, data}` envelope
- `Service/LiveSubscriberTokenService` — mints subscriber-only tokens
- `DependencyInjection/` — parameters, plus the prepended Mercure hub

## Two tokens, not one

The hub JWT configured on the `default` hub is the server's: it carries `publish: '*'` and no
subscribe claim, and never leaves the server. Subscriber tokens are minted per request by
`LiveSubscriberTokenService` from the same secret, carry a `subscribe` claim listing the exact
topics and no publish claim at all — `LcobucciFactory::create()` omits the claim entirely when
`publish` is `null`, where an empty array would grant publication on the empty selector.

Both are signed with `MERCURE_JWT_SECRET`. The hub must be started with that same value in
`MERCURE_PUBLISHER_JWT_KEY` and `MERCURE_SUBSCRIBER_JWT_KEY`.

## No dependency on the browser half

`symfony-live` requires `symfony/mercure`, `symfony/mercure-bundle`, `lcobucci/jwt` and the
Wexample helpers, and nothing from `symfony-loader`. An API-only app can use it as it is; an
app rendering pages through the loader wires the browser side itself, from the same
`subscribe-info` response.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.2
- lcobucci/jwt: ^4.0 || ^5.0
- symfony/mercure: ^0.6
- symfony/mercure-bundle: ^0.3
- wexample/php-helpers: >=3.0.0
- wexample/symfony-helpers: >=7.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
