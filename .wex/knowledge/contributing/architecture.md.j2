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
