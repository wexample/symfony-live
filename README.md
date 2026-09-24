# symfony-live

Version: 4.0.2

`symfony-live` is the server half of Wex live updates: it publishes to a Mercure hub, hands
subscribers a token scoped to the topics they asked for, and owns the topic grammar and the
payload envelope both halves have to agree on. The browser half stays where it is —
`LiveUpdatesService` in `symfony-loader`, `MercureLiveUpdatesDriver` in `js-api` — and this
package never depends on it.

## What the two halves agree on

Three things cross the wire, and all three are written here.

A **topic** is `entity/<kebab-entity-name>/<action>/<id>`, built by
`LiveTopicHelper::entity()`. The browser rebuilds the same string segment by segment through
`renderNode.liveUpdatesTopic()`, so a change to the grammar is a change on both sides.
`LiveTopicHelper::join()` prefixes a topic when an API version has to scope it.

The last segment is `(string) $entity->getId()`, the same expression the normalizers use for
the `id` they serialize — and the helper takes no identifier argument, so an app cannot pass
a second one. A subscriber only ever holds what the API served it: give it a topic built on
anything else and the two halves subscribe and publish past each other, silently.

An **update payload** is `{"event": "...", "data": ...}`, produced by
`LivePublisherService::publishEvent()`. A subscriber reads the event name to decide what to
do, instead of guessing from the shape of the data.

**Subscriber info** is `{hubUrl, jwt, topics, expiresAt}`, produced by
`LiveSubscriberTokenService::buildSubscriberInfo()` and consumed by the browser driver as its
Mercure configuration. Keys are camelCase and fixed.

## Publishing without calling anything

An entity marked `#[LiveEntity]` publishes itself. `LiveEntityPublishListener` watches
Doctrine and pushes `create`, `update` and `delete` on the entity's own topics — a service
that changes one has nothing to call.

Publication happens on `postFlush`, not from inside the transaction: a change announced and
then rolled back is a change no subscriber can un-hear.

What goes in `data` is the output of the entity's live normalizer, which an app declares by
adding an interface to a normalizer it already has:

```php
use Wexample\SymfonyLive\Interface\LiveEntityNormalizerInterface;

class DefaultMessageNormalizer extends AbstractEntityNormalizer implements LiveEntityNormalizerInterface
{
}
```

Nothing else is needed: `getEntityClassName()` is already on `AbstractEntityNormalizer`, and
the interface is autoconfigured. An entity usually has several normalizers — legacy, summary,
export — and only the one carrying the interface goes on the wire. With none, `data` is
`{"id": "..."}` and the subscriber refetches.

A `delete` always carries only the id: the entity is still in memory but its relations are
gone, and normalizing it would walk into proxies of rows that no longer exist.

Publication is scoped by the same `actions` list that the token grants, so an entity declaring
only `EVENT` is not auto-published — its events stay the app's to send.

## Publishing by hand

```php
use Wexample\SymfonyLive\Enum\LiveTopicAction;
use Wexample\SymfonyLive\Helper\LiveTopicHelper;
use Wexample\SymfonyLive\Service\LivePublisherService;

public function __construct(
    private readonly LivePublisherService $publisher,
) {
}

$this->publisher->publishEvent(
    LiveTopicHelper::entity($session, LiveTopicAction::EVENT),
    'message.save',
    $this->messageNormalizer->normalize($message)
);
```

`publish()` is there for a payload that is not an event envelope. Both return the update id
the hub answers with.

## Handing a subscriber its token

Mark the entity and the endpoint exists:

```php
use Wexample\SymfonyLive\Attribute\LiveEntity;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[LiveEntity(actions: [LiveTopicAction::EVENT])]
class Session extends AbstractEntity
{
}
```

Then include the bundle's routes once, in `config/routes.yaml`:

```yaml
wexample_symfony_live:
    resource: '@WexampleSymfonyLiveBundle/Resources/config/routes.yaml'
```

`GET /live/subscribe-info/session/<id>` now answers the `{hubUrl, jwt, topics, expiresAt}`
above. `LiveEntityRegistryService` finds the entity by reading `#[LiveEntity]` off the
Doctrine mapping — there is no name-to-class map to keep in a config file, and the name in the
URL is the same kebab name as in the topic.

Two things are worth knowing about what it grants:

- The caller names an **entity**, never a topic. The topics come back built by
  `LiveTopicHelper::entity()`, the same call the publisher makes, so a subscriber cannot end
  up listening on a topic nobody publishes to. Listing no `actions` grants all four.
- The endpoint runs `denyAccessUnlessGranted(VIEW, $entity)`. With no voter supporting the
  entity this **denies**: `#[LiveEntity]` opens an entity to subscription, it does not say
  who may listen. That stays the app's decision.

The token is replayable until it expires, so it grants exactly those topics and carries no
publish claim at all. An app that needs something else in the envelope — an API version
prefix on the topics, an internal variant of the route — calls
`LiveSubscriberTokenService::buildSubscriberInfo()` from its own controller instead.

## Configuration

The bundle prepends a `default` Mercure hub, so an app that ships the env vars needs no
`config/packages/mercure.yaml` of its own:

- `MERCURE_URL` — where Symfony publishes, reachable from the containers
- `MERCURE_PUBLIC_URL` — where the browser subscribes, handed out in `subscribeInfo`
- `MERCURE_JWT_SECRET` — signs both the publisher token and the subscriber ones

The three carry a default pointing at `http://localhost/.well-known/mercure` with the
placeholder secret of the Mercure recipe, set on the variables themselves rather than on a
config key. An app installing the bundle therefore boots — `cache:clear`, a route dump, an
image build — before anyone has declared a hub, and so does the
`config/packages/mercure.yaml` the recipe writes. Nothing reaches a hub with those values:
a deployment overrides them.

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
- [Publishing without calling anything](#publishing-without-calling-anything)
- [Publishing by hand](#publishing-by-hand)
- [Handing a subscriber its token](#handing-a-subscriber-its-token)
- [Configuration](#configuration)
- [Where the cut is](#where-the-cut-is)
- [Layout](#layout)
- [Publishing waits for postFlush](#publishing-waits-for-postflush)
- [The entity name is derived, never declared](#the-entity-name-is-derived-never-declared)
- [One identifier](#one-identifier)
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
- `Attribute/LiveEntity` — marks an entity open to subscription
- `Class/LiveSubscriberInfo` — the wire shape handed to a subscriber
- `Class/LiveEntityDefinition` — a marked entity, its kebab name and its granted actions
- `Service/LivePublisherService` — wraps `HubInterface`, owns the `{event, data}` envelope
- `Service/LiveSubscriberTokenService` — mints subscriber-only tokens
- `Service/LiveEntityRegistryService` — finds marked entities in the Doctrine mapping
- `Interface/LiveEntityNormalizerInterface` — the normalizer an entity is published with
- `EventListener/LiveEntityPublishListener` — publishes create, update and delete
- `Controller/LiveSubscribeController` — the generic `subscribe-info` endpoint
- `DependencyInjection/` — parameters, plus the prepended Mercure hub

## Publishing waits for postFlush

`postPersist`, `postUpdate` and `postRemove` only note what happened; the listener publishes in
`postFlush`. Pushing from inside the transaction would announce a change a rollback then
undoes, and there is no retraction a subscriber could hear.

Finding the normalizer is the one thing that could not be derived. An entity has several —
legacy, summary, export — none of which is more canonical than the others from outside the
app, and `symfony-helpers` normalizers are injected by class rather than resolved through the
serializer, so asking the serializer would have picked whichever matched first. Marking one
with `LiveEntityNormalizerInterface` is the app naming it, and costs a single `implements`
because `getEntityClassName()` is already there. The interface is autoconfigured onto a tag in
the extension and the listener takes them all through `#[AutowireIterator]` — no compiler pass,
and no dependency on `symfony-api`.

## The entity name is derived, never declared

`LiveEntityRegistryService` builds its name-to-class map by walking
`getMetadataFactory()->getAllMetadata()` and keeping the classes carrying `#[LiveEntity]`. There
is no config file to keep in step, and no name to invent: the key is
`ClassHelper::getKebabName()`, which is `getSnakeShortClassName()` with dashes instead of
underscores — the same string the topic's second segment already uses, and the same one the
browser produces with `stringToKebab(getEntityName())`.

The endpoint takes an entity name and an id, never a topic. A caller that could hand over a
topic list would be a caller deciding what it may listen to; here the topics are built by the
same `LiveTopicHelper::entity()` call the publisher makes, and authorisation is a voter on the
loaded entity. With no voter supporting it, Symfony denies — which is the direction a mistake
should fall in.

## One identifier

`LiveTopicHelper::entity()` reads the id off the entity and takes no identifier argument.
That is deliberate: an app given the choice takes it per call site, and the browser has no
choice at all — it subscribes on the `id` the API served it. The two only meet if the topic
carries `(string) $entity->getId()`, which is what `AbstractEntityNormalizer` serializes.

Apps carrying a second identifier from before uuids — a `secureId`, a slug — cannot publish
on it: the migration is theirs to finish, not the helper's to accommodate.

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

- php: >=8.5
- lcobucci/jwt: ^4.0 || ^5.0
- symfony/mercure: ^0.6
- symfony/mercure-bundle: ^0.3
- wexample/php-helpers: >=4.0.0
- wexample/symfony-helpers: >=9.0.0

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
