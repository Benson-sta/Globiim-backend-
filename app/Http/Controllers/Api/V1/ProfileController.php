<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'username' => ['sometimes', 'alpha_dash', 'min:3', 'max:30', Rule::unique('users', 'username')->ignore($user->id)],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ]);

        $user->fill($data)->save();

        return response()->json(['message' => 'Profile updated.', 'user' => $user->fresh()]);
    }
}
