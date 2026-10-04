<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('app:setup-admin-account')]
#[Description('Create or update the configured TaskFlow administrator account')]
class SetupAdminAccount extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) config('admin.email')));
        $name = $this->ask('Administrator name');
        $password = $this->secret('Administrator password (minimum 12 characters)');
        $passwordConfirmation = $this->secret('Confirm administrator password');

        if (! is_string($name) || trim($name) === '') {
            $this->error('An administrator name is required.');

            return self::FAILURE;
        }

        if (
            ! is_string($password)
            || mb_strlen($password) < 12
            || $password !== $passwordConfirmation
        ) {
            $this->error('Use a password of at least 12 characters and enter it identically both times.');

            return self::FAILURE;
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $user ??= new User(['email' => $email]);
        $user->forceFill([
            'name' => trim($name),
            'email' => $email,
            'password' => Hash::make($password),
        ])->save();

        $this->info("Administrator account ready for {$email}.");

        return self::SUCCESS;
    }
}
