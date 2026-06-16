<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->string('agency', 60)
                ->default('matriz-las-naves')
                ->after('storage_folder')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->dropIndex(['agency']);
            $table->dropColumn('agency');
        });
    }
};
