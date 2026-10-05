<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
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
        $projectIconPaths = glob(public_path('images/icon-new-project/*.png')) ?: [];
        sort($projectIconPaths, SORT_NATURAL);
        $projectIcons = array_map(
            fn (string $path): array => [
                'name' => basename($path),
                'url' => asset('images/icon-new-project/'.basename($path)),
            ],
            $projectIconPaths,
        );

        return response()->json([
            'data' => [
                'avatars' => $avatars,
                'icons' => $icons,
                'workspace' => $workspace,
                'project_icons' => $projectIcons,
                'images' => [
                    'welcome' => asset('images/taskflow-welcome.jpg'),
                    'favicon' => asset('favicon.ico'),
                ],
                'app_icon' => asset('taskflow-icon.svg'),
            ],
        ]);
    }
}
