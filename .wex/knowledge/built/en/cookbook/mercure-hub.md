The bundle configures Symfony's side of the hub, not the hub itself. This is the container
service an app adds next to its others, as it runs today in production:

```yaml
  mercure:
    <<: *default_service
    container_name: ${APP_PROJECT_NAME}_mercure
    extends:
      file: ${SERVICE_DEFAULT_COMPOSE}
      service: default
    image: dunglas/mercure:v0.18
    environment:
      - VIRTUAL_HOST=mercure.api.wex
      - CORS_ALLOWED_ORIGINS=*
      - PUBLISH_ALLOWED_ORIGINS=*
      - MERCURE_PUBLISHER_JWT_KEY=${MERCURE_JWT_SECRET}
      - MERCURE_SUBSCRIBER_JWT_KEY=${MERCURE_JWT_SECRET}
      - MERCURE_TRANSPORT_URL=bolt:///srv/mercure.db?size=1000&cleanup_frequency=0.3
      - SERVER_NAME=:80
      - MERCURE_EXTRA_DIRECTIVES=|
        auto_https off
        cors_origins "*"
```

## Bound the Bolt transport

`MERCURE_TRANSPORT_URL` without `size` keeps every update ever published. A hub left that way
grew `mercure.db` to 412 GB and filled the disk. `size=1000` keeps only what a reconnecting
subscriber needs to catch up, and `cleanup_frequency=0.3` is how often the hub prunes past
that bound.

## The two keys are the one secret

`MERCURE_PUBLISHER_JWT_KEY` and `MERCURE_SUBSCRIBER_JWT_KEY` are both the app's
`MERCURE_JWT_SECRET`: Symfony signs its publisher token and the subscriber tokens with it, and
the hub verifies both against these. A mismatch shows up as a 401 on the `EventSource`, which
the browser reports as a plain connection error.

## Two URLs, one hub

`MERCURE_URL` is what Symfony calls to publish, so it is the container-network address.
`MERCURE_PUBLIC_URL` is what the browser subscribes to, so it is the one behind the reverse
proxy — it is the value handed out in the `hubUrl` of a subscribe-info response.
