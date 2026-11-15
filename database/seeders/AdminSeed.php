<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The first super admin. On a server the email and password are asked for in the terminal
 * (never a known default); locally a throwaway admin is made for development.
 */
class AdminSeed extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $email = $this->command->ask('بريد المدير');
            $password = $this->command->secret('كلمة مرور المدير (8 أحرف على الأقل، فيها حروف وأرقام)');
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 8
                || ! preg_match('/\pL/u', $password) || ! preg_match('/\d/', $password)) {
                $this->command->error('بريد غير صالح أو كلمة مرور ضعيفة: لم يُنشأ المدير.');

                return;
            }
        } else {
            [$email, $password] = ['admin1@gmail.com', '1234'];
        }

        $admin = User::firstOrCreate(['email' => $email], ['name' => 'admin', 'password' => Hash::make($password)]);
        $admin->assignRole('super-admin');
    }
}
