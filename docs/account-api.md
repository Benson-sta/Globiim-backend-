# Globiim Account API v1

Base path: `/api/v1`

All request and response bodies use JSON. Authenticated routes require:
`Authorization: Bearer <token>`

## Register

`POST /auth/register`

```json
{
  "name": "Jane Creator",
  "username": "janecreator",
  "email": "jane@example.com",
  "password": "a-strong-password",
  "password_confirmation": "a-strong-password",
  "account_type": "creator",
  "device_name": "Android phone"
}
```

Use either `email` or `phone`. Allowed account types: `individual`, `creator`, `business`, `manufacturer`.

## Login

`POST /auth/login`

```json
{
  "login": "jane@example.com",
  "password": "a-strong-password",
  "device_name": "Android phone"
}
```

The `login` field accepts email, phone number or username.

## Current account and logout

- `GET /auth/me` — authenticated user details.
- `POST /auth/logout` — revoke the current access token.

## Profile

- `GET /profile`
- `PATCH /profile` — accepts `name`, `username`, `bio` and `avatar_url`.

## Password recovery

- `POST /auth/password/forgot` — accepts `email`.
- `POST /auth/password/reset` — accepts `email`, `token`, `password`, and `password_confirmation`.

## Email and phone verification

- `POST /auth/verification/request` — accepts `channel` (`email` or `phone`) and `destination`.
- `POST /auth/verification/confirm` — accepts `channel`, `destination` and six-digit `code`.

**Delivery status:** Email delivery requires valid SMTP settings. SMS delivery is not connected yet; configure an SMS provider before enabling phone OTP in production.

## Setup checklist

1. Install PHP 8.2+ and Composer.
2. Run `composer install`.
3. Copy `.env.example` to `.env` and set a strong unique `APP_KEY` using `php artisan key:generate`.
4. Create a MySQL database and set `DB_*` values.
5. Configure SMTP credentials.
6. Run `php artisan migrate`.
7. Run `php artisan route:list --path=api/v1` and test endpoints over HTTPS.

This repository is the source foundation. The API is not production-ready until these setup steps are completed and the tests pass on the target hosting environment.
