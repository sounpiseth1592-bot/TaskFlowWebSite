<?php

namespace App\Http\Resources;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
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
            ...UserResource::make($user)->resolve($request),
            'projects' => $user->projects()
                ->withCount([
                    'tasks',
                    'tasks as open_tasks_count' => fn ($query) => $query->where('done', false),
                ])
                ->get(),
            'tasks' => Task::query()
                ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
                ->latest()
                ->get(),
        ];
    }
}
