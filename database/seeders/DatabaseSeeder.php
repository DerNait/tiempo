<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CategoryProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotent and safe to run in production: it creates the personal account if
 * it does not exist yet and tops up its default categories. It never deletes
 * or overwrites anything.
 */
class DatabaseSeeder extends Seeder
{
    public function run(CategoryProvisioner $provisioner): void
    {
        $email = config('tiempo.personal_user.email');

        if (blank($email)) {
            $this->command?->warn('PERSONAL_USER_EMAIL no está configurado; no se creó ningún usuario.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => config('tiempo.personal_user.name') ?: 'DerNait',
                'password' => config('tiempo.personal_user.password') ?: Str::password(24),
                'timezone' => config('tiempo.timezone'),
                'week_starts_on' => 1,
            ],
        );

        $provisioner->seedDefaults($user);

        $this->command?->info("Cuenta lista para {$user->email} con ".$user->categories()->count().' categorías.');
    }
}
