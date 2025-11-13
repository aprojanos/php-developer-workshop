# Traffic Safety Management System

## Authentication

- `POST /api/auth/login` returns a short-lived access token and a refresh token.
- `POST /api/auth/refresh` rotates a valid refresh token and issues a new access token pair.
- `POST /api/auth/logout` revokes the provided refresh token, or all tokens when `invalidateAll` is `true`.

All other API routes require the `Authorization: Bearer <access_token>` header. The token must be a valid JWT issued by the login or refresh endpoints and the caller must have the role required by the route (e.g. admin-only user management).

### Environment

- `JWT_SECRET` – symmetric key used to sign access tokens (required).
- `JWT_TTL` – access token lifetime in seconds (default: `3600`).
- `REFRESH_TOKEN_TTL` – refresh token lifetime in seconds (default: `1209600`, 14 days).

### Event Bus

Domain events are published through RabbitMQ. The Docker environment includes a RabbitMQ broker (port `5672`) with the management UI on `15672`. Configure the application with the following environment variables when running outside of Docker:

- `RABBITMQ_HOST` – broker host (default: `localhost`).
- `RABBITMQ_PORT` – broker port (default: `5672`).
- `RABBITMQ_USER` – broker username (default: `guest`).
- `RABBITMQ_PASSWORD` – broker password (default: `guest`).
- `RABBITMQ_VHOST` – broker vhost (default: `/`).
- `RABBITMQ_EXCHANGE` – exchange used to broadcast domain events (default: `domain_events`).
- `RABBITMQ_QUEUE` – queue bound to the exchange for local consumers (default: `domain_events`).

Start the domain-event consumer with:

```
docker compose exec php-cli php bin/consume-domain-events.php
```

This command keeps running, pulling events from RabbitMQ and invoking any listeners that were registered (for example, project evaluations triggered by `AccidentCreatedEvent`).

Refresh tokens are stored in the `user_refresh_tokens` table so they can be revoked or rotated server-side. Access tokens are tracked in `user_access_tokens`; logout revokes the active token, and `invalidateAll` also revokes the rest of the user's outstanding access tokens.