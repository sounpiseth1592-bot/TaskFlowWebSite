<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TokenController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (
                        is_string($value)
                        && (
                            strcasecmp($value, (string) config('admin.email')) === 0
                            || User::whereRaw('LOWER(email) = ?', [Str::lower($value)])->exists()
                        )
                    ) {
                        $fail(strcasecmp($value, (string) config('admin.email')) === 0
                            ? 'This email is reserved for the configured administrator account.'
                            : 'The email has already been taken.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return $this->tokenResponse($request, $user, $data['device_name'] ?? 'Postman', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);
        $user = User::whereRaw('LOWER(email) = ?', [$data['email']])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        return $this->tokenResponse($request, $user, $data['device_name'] ?? 'Postman');
    }

    private function tokenResponse(
        Request $request,
        User $user,
        string $deviceName,
        int $status = 200,
    ): JsonResponse {
        $token = $user->createToken($deviceName, ['*'], now()->addDays(30));

        return response()->json([
            'message' => $status === 201 ? 'Account created successfully.' : 'Signed in successfully.',
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => AccountResource::make($user)->resolve($request),
        ], $status);
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
    }
}
