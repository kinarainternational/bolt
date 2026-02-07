<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('app.admin_email', 'happy@kinaraexports.com');
        $password = config('app.admin_password', 'password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Happy',
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Admin user created/updated: {$email}");
    }
}
