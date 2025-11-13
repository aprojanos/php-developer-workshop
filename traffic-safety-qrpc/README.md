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

Refresh tokens are stored in the `user_refresh_tokens` table so they can be revoked or rotated server-side. Access tokens are tracked in `user_access_tokens`; logout revokes the active token, and `invalidateAll` also revokes the rest of the user's outstanding access tokens.

## gRPC Accident Service

- The proto contract lives in `proto/accident_service.proto` and exposes the `AccidentService.All` RPC for retrieving the full accident catalogue.
- Generated PHP message/client stubs are stored in `generated/php/Traffic/Grpc/Accident/V1` and are autoloaded via Composer.
- `ACCIDENT_GRPC_ENDPOINT` controls whether the hotspot bounded context calls the remote accident service (`host:port` string) or falls back to the in-process provider when unset.

### Regenerating PHP stubs

Docker is the recommended approach because it bundles the gRPC plugins:

```bash
docker run --rm -v "$(pwd)":/defs namely/protoc-all \
  -d proto \
  -o generated/php \
  -l php \
  --with_grpc
composer dump-autoload
```

This command regenerates the protobuf messages, enum, metadata, and `AccidentServiceClient`. Re-run it whenever the proto definition changes.

### Verifying the integration

- Run `composer install` (root and `contexts/hotspot/`) to pull the `grpc/grpc` and `google/protobuf` dependencies.
- Execute `./vendor/bin/phpunit tests/HotspotServiceTest.php` to ensure the hotspot application still behaves correctly after regeneration.