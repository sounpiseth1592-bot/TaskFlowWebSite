<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->whereRaw('LOWER(email) != ?', [mb_strtolower((string) config('admin.email'))])
            ->with('projects.tasks')
            ->orderBy('id')
            ->get();

        return PublicUserResource::collection($users);
    }

    public function adminProfile(): JsonResponse
    {
        $admin = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) config('admin.email'))])
            ->firstOrFail(['name', 'avatar_preset', 'avatar_path', 'google_avatar_url']);

        return response()->json([
            'data' => [
                'name' => $admin->name,
                'avatar_url' => $admin->profilePhotoUrl(),
            ],
        ]);
    }
}
