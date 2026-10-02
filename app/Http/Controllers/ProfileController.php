<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ProfileController extends Controller
{
    public function revokeApiToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out successfully.']);
    }

    public function edit(Request $request): View
    {
        return view('settings', [
            'user' => $request->user(),
            'avatarPresets' => User::PROFILE_AVATAR_PRESETS,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'avatar_action' => ['sometimes', Rule::in(['preset', 'upload', 'google', 'initials'])],
            'avatar_preset' => [
                'exclude_unless:avatar_action,preset',
                'nullable',
                'required',
                Rule::in(User::PROFILE_AVATAR_PRESETS),
            ],
            'avatar_file' => [
                'exclude_unless:avatar_action,upload',
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width=1024,max_height=1024',
            ],
        ]);

        $avatarAction = $data['avatar_action'] ?? null;

        if ($avatarAction === 'google' && ! $user->google_avatar_url) {
            throw ValidationException::withMessages([
                'avatar_action' => 'A Google profile photo is not available for this account.',
            ]);
        }

        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        if ($avatarAction === 'upload') {
            $newAvatarPath = $request->file('avatar_file')->storePublicly('avatars/'.$user->id, 'public');

            if (! $newAvatarPath) {
                throw new RuntimeException('The profile photo could not be saved.');
            }
        }

        $attributes = ['name' => trim($data['name'])];

        if ($avatarAction !== null) {
            $attributes['avatar_preset'] = $avatarAction === 'preset' ? $data['avatar_preset'] : null;
            $attributes['avatar_path'] = $avatarAction === 'upload' ? $newAvatarPath : null;
        }

        $user->forceFill($attributes)->save();

        if ($avatarAction !== null && $oldAvatarPath && $oldAvatarPath !== $newAvatarPath) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_action' => $user->profileAvatarSelection(),
                'avatar_preset' => $user->avatar_preset,
                'avatar_url' => $user->profilePhotoUrl(),
            ],
        ]);
    }
}
