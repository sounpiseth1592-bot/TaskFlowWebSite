<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TokenController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
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
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);
        $user = User::where('email', $data['email'])->first();

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
            'user' => UserResource::make($user)->resolve($request),
        ], $status);
    }
}
