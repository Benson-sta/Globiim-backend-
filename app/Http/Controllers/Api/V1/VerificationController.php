<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,phone'],
            'destination' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        if ($data['channel'] === 'email' && $user && $user->email !== $data['destination']) {
            throw ValidationException::withMessages(['destination' => ['Use the email address on your account.']]);
        }
        if ($data['channel'] === 'phone' && $user && $user->phone !== $data['destination']) {
            throw ValidationException::withMessages(['destination' => ['Use the phone number on your account.']]);
        }

        $user = $user ?: User::query()->where($data['channel'], $data['destination'])->first();
        if (!$user) {
            return response()->json(['message' => 'If the account exists, a verification code will be sent.']);
        }

        $code = (string) random_int(100000, 999999);
        VerificationCode::query()->where('user_id', $user->id)->whereNull('consumed_at')->delete();
        VerificationCode::create([
            'user_id' => $user->id,
            'channel' => $data['channel'],
            'destination' => $data['destination'],
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        // Delivery is intentionally routed through Laravel notifications.
        // Configure the email/SMS provider before enabling production verification.
        if ($data['channel'] === 'email') {
            $user->notify(new \App\Notifications\AccountVerificationNotification($code));
        } else {
            logger()->warning('Globiim SMS provider is not configured; OTP not delivered.', ['user_id' => $user->id]);
        }

        return response()->json(['message' => 'If the account exists, a verification code will be sent.']);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,phone'],
            'destination' => ['required', 'string', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user() ?: User::query()->where($data['channel'], $data['destination'])->first();
        if (!$user) {
            throw ValidationException::withMessages(['code' => ['The verification code is invalid or expired.']]);
        }

        $record = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('channel', $data['channel'])
            ->where('destination', $data['destination'])
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (!$record || $record->expires_at->isPast() || $record->attempts >= 5 || !Hash::check($data['code'], $record->code_hash)) {
            if ($record) {
                $record->increment('attempts');
            }
            throw ValidationException::withMessages(['code' => ['The verification code is invalid or expired.']]);
        }

        $record->update(['consumed_at' => now()]);
        $user->forceFill([$data['channel'].'_verified_at' => now()])->save();

        return response()->json(['message' => ucfirst($data['channel']).' verified successfully.']);
    }
}
