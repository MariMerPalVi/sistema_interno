<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->boolean('requires_spouse_documents')->default(false)->after('storage_folder');
        });

        Schema::table('internal_document_templates', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('file_name_pattern');
        });
    }

    public function down(): void
    {
        Schema::table('internal_document_templates', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('account_openings', function (Blueprint $table) {
            $table->dropColumn('requires_spouse_documents');
        });
    }
};
