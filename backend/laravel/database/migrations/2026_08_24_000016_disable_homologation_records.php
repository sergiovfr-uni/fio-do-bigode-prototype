<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $emails = [
        'carlos.hml@fiodobigode.com.br',
        'mariana.hml@fiodobigode.com.br',
        'rafael.hml@fiodobigode.com.br',
    ];

    private array $listingIds = [
        '11111111-1111-4111-8111-111111111111',
        '22222222-2222-4222-8222-222222222222',
        '33333333-3333-4333-8333-333333333333',
    ];

    public function up(): void
    {
        DB::table('users')->whereIn('email', $this->emails)->update([
            'account_status'=>'blocked',
            'updated_at'=>now(),
        ]);

        DB::table('listings')->whereIn('public_id', $this->listingIds)->update([
            'status'=>'archived',
            'updated_at'=>now(),
        ]);

        DB::table('campaigns')->where('name', 'Campanha Home Homologação')->update([
            'active'=>false,
            'updated_at'=>now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->whereIn('email', $this->emails)->update([
            'account_status'=>'active',
            'updated_at'=>now(),
        ]);

        DB::table('listings')->whereIn('public_id', $this->listingIds)->update([
            'status'=>'published',
            'updated_at'=>now(),
        ]);
    }
};
