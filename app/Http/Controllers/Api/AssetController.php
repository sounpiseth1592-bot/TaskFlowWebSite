<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
    /**
     * @return list<array{name: string, url: string}>
     */
    public static function projectIconAssets(): array
    {
        $supportedExtensions = ['gif', 'jpeg', 'jpg', 'png', 'svg', 'webp'];
        $iconPaths = glob(public_path('images/icon-new-project/*')) ?: [];
        $iconPaths = array_filter(
            $iconPaths,
            fn (string $path): bool => is_file($path)
                && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $supportedExtensions, true),
        );
        sort($iconPaths, SORT_NATURAL | SORT_FLAG_CASE);

        return array_map(
            fn (string $path): array => [
                'name' => basename($path),
                'url' => asset('images/icon-new-project/'.basename($path)),
            ],
            $iconPaths,
        );
    }

    public function projectIcons(): JsonResponse
    {
        return response()->json(['data' => self::projectIconAssets()]);
    }

    /**
     * @return array<string, string>
     */
    public static function workspaceIconPaths(): array
    {
        return [
            'projects' => 'images/icons/Project_taskflow.png',
            'tasks' => 'images/icons/Task_taskflow.png',
            'in_progress' => 'images/icons/inProgress_taskflow.png',
            'due_today' => 'images/icons/dueToday_taskflow.png',
            'overdue' => 'images/icons/Passdue_taskflow.png',
            'completed' => 'images/icons/complete_taskflow.png',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function workspaceIconUrls(): array
    {
        return array_map(
            fn (string $path): string => asset($path),
            self::workspaceIconPaths(),
        );
    }

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
        $workspace = array_map(
            fn (string $name, string $path): array => [
                'name' => $name,
                'url' => asset($path),
            ],
            array_keys(self::workspaceIconPaths()),
            array_values(self::workspaceIconPaths()),
        );

        return response()->json([
            'data' => [
                'avatars' => $avatars,
                'icons' => $icons,
                'workspace' => $workspace,
                'project_icons' => self::projectIconAssets(),
                'images' => [
                    'welcome' => asset('images/taskflow-welcome.jpg'),
                    'favicon' => asset('favicon.ico'),
                ],
                'app_icon' => asset('taskflow-icon.svg'),
            ],
        ]);
    }
}
