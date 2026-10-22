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
