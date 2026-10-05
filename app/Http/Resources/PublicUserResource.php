<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'description' => $user->description,
            'avatar_url' => $user->profilePhotoUrl(),
            'projects' => $user->projects->map(
                fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'color' => $project->color,
                    'description' => $project->description,
                    'icon' => $project->icon,
                    'icon_url' => asset('images/icon-new-project/'.$project->icon),
                    'tasks' => $project->tasks->map(
                        fn (Task $task): array => [
                            'id' => $task->id,
                            'title' => $task->title,
                            'notes' => $task->notes,
                            'due_date' => $task->due_date?->toISOString(),
                            'priority' => $task->priority,
                            'done' => $task->done,
                            'subtasks' => $task->subtasks ?? [],
                        ],
                    ),
                ],
            ),
        ];
    }
}
