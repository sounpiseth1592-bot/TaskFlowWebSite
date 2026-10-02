<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $avatars = array_map(
            fn (string $preset): array => [
                'name' => $preset,
                'url' => asset('images/icons/'.$preset.'.png'),
            ],
            User::PROFILE_AVATAR_PRESETS,
        );

        $iconPaths = [
            'focus' => 'images/icons/focus.svg',
            'projects' => 'images/icons/projects.svg',
            'progress' => 'images/icons/progress.svg',
            'reminders' => 'images/icons/reminders.svg',
            'english' => 'images/icons/englishs.jpg',
            'cambodia' => 'images/icons/cambodias.png',
        ];
        $icons = array_map(
            fn (string $name, string $path): array => [
                'name' => $name,
                'url' => asset($path),
            ],
            array_keys($iconPaths),
            array_values($iconPaths),
        );

        return response()->json([
            'data' => [
                'avatars' => $avatars,
                'icons' => $icons,
                'images' => [
                    'welcome' => asset('images/taskflow-welcome.jpg'),
                    'favicon' => asset('favicon.ico'),
                ],
                'app_icon' => asset('taskflow-icon.svg'),
            ],
        ]);
    }
}
