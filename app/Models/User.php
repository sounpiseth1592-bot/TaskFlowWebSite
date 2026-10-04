<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'google_id', 'email_verified_at', 'avatar_preset', 'avatar_path', 'google_avatar_url', 'description'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const PROFILE_AVATAR_PRESETS = [
        'people1', 'people2', 'people3', 'people4', 'people5', 'people6', 'people7',
        'people8', 'people9', 'people10', 'people11', 'people12', 'people13',
    ];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function profileAvatarSelection(): string
    {
        if ($this->avatar_path) {
            return 'upload';
        }

        if ($this->avatar_preset) {
            return 'preset';
        }

        return $this->google_avatar_url ? 'google' : 'initials';
    }

    public function profilePhotoUrl(): ?string
    {
        if ($this->avatar_path) {
            return Storage::disk('public')->url($this->avatar_path);
        }

        if (in_array($this->avatar_preset, self::PROFILE_AVATAR_PRESETS, true)) {
            return asset('images/icons/'.$this->avatar_preset.'.png');
        }

        return $this->google_avatar_url;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
