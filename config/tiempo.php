<?php

return [
    /*
     * Timezone every local day and week boundary is computed in when a user
     * has no explicit preference.
     */
    'timezone' => env('APP_USER_TIMEZONE', 'America/Guatemala'),

    /*
     * The single account provisioned by DatabaseSeeder. Passwords come from
     * the environment; nothing is hardcoded in the repository.
     */
    'personal_user' => [
        'name' => env('PERSONAL_USER_NAME', 'DerNait'),
        'email' => env('PERSONAL_USER_EMAIL'),
        'password' => env('PERSONAL_USER_PASSWORD'),
    ],
];
