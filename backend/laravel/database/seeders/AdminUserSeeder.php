<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

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
            $this->command?->warn("Usuário {$email} não encontrado; cadastre a conta antes de habilitar o painel.");
            return;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->command?->info("Acesso administrativo habilitado para {$email}.");
    }
}
