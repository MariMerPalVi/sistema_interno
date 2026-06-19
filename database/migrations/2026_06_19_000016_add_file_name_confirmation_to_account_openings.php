<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->boolean('file_name_confirmed')->default(false)->after('file_name');
        });

        DB::table('account_openings')->update(['file_name_confirmed' => true]);
    }

    public function down(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->dropColumn('file_name_confirmed');
        });
    }
};
