<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HomologationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('users')->updateOrInsert(
            ['email' => 'carlos.hml@fiodobigode.com.br'],
            [
                'name' => 'Carlos Mendes',
                'cpf' => '11111111111',
                'phone' => '31999990001',
                'password' => Hash::make('Bigode@2026'),
                'kyc_status' => 'verified',
                'risk_score' => 16,
                'reputation_score' => 94,
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'mariana.hml@fiodobigode.com.br'],
            [
                'name' => 'Mariana Souza',
                'cpf' => '22222222222',
                'phone' => '31999990002',
                'password' => Hash::make('Bigode@2026'),
                'kyc_status' => 'verified',
                'risk_score' => 21,
                'reputation_score' => 91,
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'rafael.hml@fiodobigode.com.br'],
            [
                'name' => 'Rafael Lima',
                'cpf' => '33333333333',
                'phone' => '31999990003',
                'password' => Hash::make('Bigode@2026'),
                'kyc_status' => 'verified',
                'risk_score' => 12,
                'reputation_score' => 96,
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $carlos = DB::table('users')->where('email','carlos.hml@fiodobigode.com.br')->value('id');
        $mariana = DB::table('users')->where('email','mariana.hml@fiodobigode.com.br')->value('id');
        $rafael = DB::table('users')->where('email','rafael.hml@fiodobigode.com.br')->value('id');

        $listings = [
            ['public_id'=>'11111111-1111-4111-8111-111111111111','seller_id'=>$carlos,'category'=>'Veículo','title'=>'Honda HR-V EX 2022','description'=>'Automático, revisado e documentação em dia. Anúncio de homologação integrado à API.','price'=>112900.00],
            ['public_id'=>'22222222-2222-4222-8222-222222222222','seller_id'=>$mariana,'category'=>'Moto','title'=>'Yamaha XMAX 250 2023','description'=>'Baixa quilometragem e revisões realizadas. Anúncio de homologação integrado à API.','price'=>28900.00],
            ['public_id'=>'33333333-3333-4333-8333-333333333333','seller_id'=>$rafael,'category'=>'Eletrônico','title'=>'MacBook Air M2 256GB','description'=>'Excelente estado, carregador original. Anúncio de homologação integrado à API.','price'=>6450.00],
        ];

        foreach ($listings as $listing) {
            DB::table('listings')->updateOrInsert(
                ['public_id' => $listing['public_id']],
                array_merge($listing, [
                    'status' => 'published',
                    'published_at' => $now,
                    'expires_at' => $now->copy()->addDays(30),
                    'updated_at' => $now,
                    'created_at' => $now,
                ])
            );
        }

        DB::table('advertisers')->updateOrInsert(
            ['name' => 'Parceiro Homologação'],
            [
                'document' => '00000000000100',
                'contact_email' => 'parceiro@fiodobigode.com.br',
                'active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $advertiserId = DB::table('advertisers')->where('name','Parceiro Homologação')->value('id');

        DB::table('campaigns')->updateOrInsert(
            ['name' => 'Campanha Home Homologação'],
            [
                'advertiser_id' => $advertiserId,
                'headline' => 'Negocie com mais informação e registre tudo no Fio do Bigode.',
                'cta' => 'Conhecer parceiro',
                'target_url' => 'https://fiodobigode.com.br',
                'placement' => 'home',
                'media_path' => null,
                'priority' => 200,
                'starts_at' => $now->copy()->subDay(),
                'ends_at' => $now->copy()->addMonths(3),
                'active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }
}
