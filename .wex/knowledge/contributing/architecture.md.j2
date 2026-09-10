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
