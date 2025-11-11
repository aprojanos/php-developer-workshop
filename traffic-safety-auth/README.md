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