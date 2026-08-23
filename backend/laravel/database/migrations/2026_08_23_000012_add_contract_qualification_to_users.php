<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identity_document', 40)->nullable()->after('cpf');
            $table->date('birth_date')->nullable()->after('identity_document');
            $table->string('marital_status', 40)->nullable()->after('birth_date');
            $table->string('occupation', 120)->nullable()->after('marital_status');
            $table->string('nationality', 60)->nullable()->after('occupation');
            $table->string('address_line', 220)->nullable()->after('phone');
            $table->string('address_number', 30)->nullable()->after('address_line');
            $table->string('address_complement', 100)->nullable()->after('address_number');
            $table->string('district', 100)->nullable()->after('address_complement');
            $table->string('city', 100)->nullable()->after('district');
            $table->string('state', 2)->nullable()->after('city');
            $table->string('postal_code', 8)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['identity_document','birth_date','marital_status','occupation','nationality','address_line','address_number','address_complement','district','city','state','postal_code']);
        });
    }
};
