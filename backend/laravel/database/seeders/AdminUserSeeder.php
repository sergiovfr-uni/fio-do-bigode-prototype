<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower(trim((string) env('ADMIN_EMAIL')));

        if ($email === '') {
            $this->command?->warn('ADMIN_EMAIL não configurado; nenhum administrador foi promovido.');
            return;
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            $password = (string) env('ADMIN_PASSWORD');
            if ($password === '') {
                $this->command?->warn("Usuário {$email} não encontrado e ADMIN_PASSWORD não foi configurada.");
                return;
            }

            $index = 0;
            do {
                $internalCpf = (string) (90000000000 + $index++);
            } while (User::where('cpf', $internalCpf)->exists());

            $user = User::create([
                'name' => (string) env('ADMIN_NAME', 'Administração Fio do Bigode'),
                'cpf' => $internalCpf,
                'email' => $email,
                'phone' => '00000000000',
                'password' => Hash::make($password),
                'kyc_status' => 'not_applicable',
                'risk_score' => 0,
                'reputation_score' => 0,
                'account_status' => 'active',
            ]);
        }

        $user->forceFill(['is_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $this->command?->info("Acesso administrativo habilitado para {$email}.");
    }
}
