<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deal_witnesses', function (Blueprint $table) {
            $table->string('invitation_code', 12)->nullable()->unique()->after('email');
            $table->string('invitation_status', 20)->default('pending')->after('invitation_code');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_status');
            $table->timestamp('viewed_at')->nullable()->after('invitation_expires_at');
        });

        DB::table('deal_witnesses')->orderBy('id')->get()->each(function ($witness) {
            do {
                $code = Str::upper(Str::random(10));
            } while (DB::table('deal_witnesses')->where('invitation_code', $code)->exists());

            DB::table('deal_witnesses')->where('id', $witness->id)->update([
                'invitation_code' => $code,
                'invitation_expires_at' => now()->addDays(30),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('deal_witnesses', function (Blueprint $table) {
            $table->dropUnique(['invitation_code']);
            $table->dropColumn(['invitation_code', 'invitation_status', 'invitation_expires_at', 'viewed_at']);
        });
    }
};
