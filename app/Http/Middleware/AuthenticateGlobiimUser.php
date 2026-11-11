<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGlobiimUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // Keep existing first-party Sanctum clients working.
        $sanctumUser = Auth::guard('sanctum')->user();
        if ($sanctumUser) {
            $request->setUserResolver(fn () => $sanctumUser);
            return $next($request);
        }

        $token = $request->bearerToken();
        $supabaseUrl = rtrim((string) config('services.supabase.url'), '/');
        $publishableKey = (string) config('services.supabase.publishable_key');

        if (!$token || !$supabaseUrl || !$publishableKey) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        try {
            // Ask Supabase Auth to validate the JWT; never trust decoded browser claims.
            $response = Http::acceptJson()
                ->withHeaders(['apikey' => $publishableKey])
                ->withToken($token)
                ->timeout(8)
                ->get($supabaseUrl.'/auth/v1/user');
        } catch (ConnectionException) {
            return new JsonResponse(['message' => 'Identity provider is temporarily unavailable.'], 503);
        }

        if (!$response->successful() || !is_string($response->json('id'))) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $identity = $response->json();
        $supabaseId = (string) $identity['id'];
        $email = is_string($identity['email'] ?? null) ? strtolower(trim($identity['email'])) : null;
        $phone = is_string($identity['phone'] ?? null) ? trim($identity['phone']) : null;

        $user = User::query()->where('supabase_user_id', $supabaseId)->first();

        if (!$user && $email) {
            $user = User::query()->where('email', $email)->first();
            // Never silently attach one identity to a different already-linked account.
            if ($user && $user->supabase_user_id && $user->supabase_user_id !== $supabaseId) {
                return new JsonResponse(['message' => 'This email is linked to a different Globiim identity. Contact support.'], 409);
            }
        }

        $metadata = is_array($identity['user_metadata'] ?? null) ? $identity['user_metadata'] : [];
        $name = trim((string) ($metadata['full_name'] ?? $metadata['name'] ?? ($email ? strstr($email, '@', true) : 'Globiim user')));
        if ($name === '') $name = 'Globiim user';

        if ($email) {
            $emailOwner = User::query()->where('email', $email)
                ->when($user, fn ($query) => $query->where('id', '!=', $user->id))
                ->exists();
            if ($emailOwner) {
                return new JsonResponse(['message' => 'This email is already attached to another Globiim account. Contact support.'], 409);
            }
        }

        $candidate = (string) ($metadata['username'] ?? '');
        $username = Str::lower(Str::slug($candidate ?: $name, '_'));
        if (!preg_match('/^[A-Za-z0-9_-]{3,30}$/', $username)) {
            $username = 'user_'.substr(str_replace('-', '', $supabaseId), 0, 12);
        }

        if (!$user) {
            $base = substr($username, 0, 24);
            $username = $base;
            $suffix = 1;
            while (User::query()->where('username', $username)->exists()) {
                $username = substr($base, 0, 25).$suffix++;
            }
            $user = new User();
            $user->username = $username;
            $user->password = Str::random(64);
            $user->status = 'active';
        }

        $accountType = $metadata['account_type'] ?? 'individual';
        if (!in_array($accountType, ['individual', 'creator', 'business', 'manufacturer'], true)) {
            $accountType = 'individual';
        }

        $user->supabase_user_id = $supabaseId;
        $user->name = $user->name ?: $name;
        $user->email = $email ?: $user->email;
        if ($phone && (!$user->phone || $user->phone === $phone) && !User::query()->where('phone', $phone)->when($user->exists, fn ($query) => $query->whereKeyNot($user->id))->exists()) $user->phone = $phone;
        $user->account_type = $user->account_type ?: $accountType;
        $user->email_verified_at = ($identity['email_confirmed_at'] ?? null) ? ($user->email_verified_at ?: now()) : $user->email_verified_at;
        $user->phone_verified_at = ($identity['phone_confirmed_at'] ?? null) ? ($user->phone_verified_at ?: now()) : $user->phone_verified_at;
        $user->save();

        $request->attributes->set('globiim.auth_provider', 'supabase');
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
