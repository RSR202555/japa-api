<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Criar roles
        $adminRole  = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $alunoRole  = Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        // Criar admin padrão (alterar senha em produção!)
        $admin = User::firstOrCreate(
            ['email' => 'japa@japatreinador.com.br'],
            [
                'name'      => 'Japa Treinador',
                'password'  => Hash::make(env('ADMIN_DEFAULT_PASSWORD', 'Tr3in@dor!2024')),
                'is_active' => true,
            ]
        );
        $admin->assignRole('admin');

        // Criar planos iniciais
        Plan::firstOrCreate(['slug' => 'basico'], [
            'name'          => 'Básico',
            'description'   => 'Plano essencial para começar sua jornada fitness.',
            'price'         => 97.00,
            'duration_days' => 30,
            'features'      => [
                'Anamnese completa',
                'Acesso ao dashboard',
                'Registro de refeições',
                'CRUD de metas',
            ],
            'is_active' => true,
        ]);

        Plan::firstOrCreate(['slug' => 'premium'], [
            'name'          => 'Premium',
            'description'   => 'Consultoria completa com acompanhamento personalizado.',
            'price'         => 197.00,
            'duration_days' => 30,
            'features'      => [
                'Tudo do plano Básico',
                'Registro de fotos de evolução',
                'Chat direto com o treinador',
                'Relatórios avançados',
                'Suporte prioritário',
            ],
            'is_active' => true,
        ]);
    }
}
