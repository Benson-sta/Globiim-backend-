# Frontend ↔ Laravel API connection

The React/Vite frontend continues to use Supabase Auth for account sessions and Supabase for the existing RLS-protected data workflows. The Laravel API accepts the same Supabase access token after validating it with Supabase Auth's `/auth/v1/user` endpoint. Existing Laravel Sanctum clients remain supported.

## Frontend build configuration

Set `VITE_API_BASE_URL` to the Laravel API origin, without a trailing slash, for example:

```env
VITE_API_BASE_URL=https://api.your-domain.example
```

The frontend API client already sends the current Supabase access token as `Authorization: Bearer <token>`. Laravel's API routes are served under `/api`, so frontend calls should use paths beginning with `/api/v1/`.

## Laravel environment

Set these server-side values:

```env
SUPABASE_URL=https://YOUR_PROJECT_REF.supabase.co
SUPABASE_PUBLISHABLE_KEY=YOUR_SUPABASE_PUBLISHABLE_KEY
CORS_ALLOWED_ORIGINS=https://YOUR_FRONTEND_ORIGIN
```

The publishable key is not a service-role secret. Never place a Supabase service-role key in the frontend or use it for this identity check.

## Identity bridge

- Laravel validates every Supabase bearer token against Supabase Auth; it does not trust an unverified JWT payload.
- The migration adds a unique nullable `users.supabase_user_id` link.
- A verified Supabase identity is linked to an existing Laravel user by email only if that Laravel user is not already linked to another Supabase identity.
- The custom API middleware continues to accept existing Sanctum personal-access tokens.
- Supabase remains authoritative for sign-in, sign-out, password reset, MFA and email/phone verification. Laravel stores the local Globiim API profile and module records.

## Hosting-independent operation

This connection does not depend on Vercel. The frontend can be built and served from any static host or CDN; Laravel can run on any PHP 8.2+/Laravel 12 host with MySQL. The frontend build must receive `VITE_API_BASE_URL`, and Laravel must receive the server environment above. GitHub stores source code; it does not itself run the PHP API or host the database.

## Deployment and rollout

1. Configure the Laravel environment and MySQL database.
2. Back up the database, then run the new migration once.
3. Deploy Laravel and verify its `/up` health endpoint and CORS origin.
4. Build the frontend with `VITE_API_BASE_URL` set to the Laravel API origin.
5. Run the consolidated integration, security and device test plan before production launch.

Do not remove the existing Supabase frontend workflows or switch off Sanctum compatibility during rollout.
