<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_check_evidences', function (Blueprint $table) {
            $table->string('subject_key', 40)->default('titular')->after('external_check_item_id');
        });

        DB::table('external_check_evidences')
            ->join('account_openings', 'account_openings.id', '=', 'external_check_evidences.account_opening_id')
            ->join('account_types', 'account_types.id', '=', 'account_openings.account_type_id')
            ->where('account_types.slug', 'cuenta-junior')
            ->update(['external_check_evidences.subject_key' => 'representante']);

        DB::table('external_check_evidences')
            ->join('account_openings', 'account_openings.id', '=', 'external_check_evidences.account_opening_id')
            ->join('account_types', 'account_types.id', '=', 'account_openings.account_type_id')
            ->where('account_types.slug', 'cuenta-juridica')
            ->update(['external_check_evidences.subject_key' => 'representante_legal']);

        Schema::table('external_check_evidences', function (Blueprint $table) {
            $table->unique(
                ['account_opening_id', 'external_check_item_id', 'subject_key'],
                'opening_external_item_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('external_check_evidences', function (Blueprint $table) {
            $table->dropUnique('opening_external_item_subject_unique');
            $table->dropColumn('subject_key');
        });
    }
};
