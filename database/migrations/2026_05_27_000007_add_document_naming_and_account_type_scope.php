<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_type_requirements', function (Blueprint $table) {
            $table->string('file_name_pattern')->nullable()->after('label');
            $table->boolean('active')->default(true)->after('is_required');
        });

        Schema::table('internal_document_templates', function (Blueprint $table) {
            $table->foreignId('account_type_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('file_name_pattern')->nullable()->after('template_path');
        });
    }

    public function down(): void
    {
        Schema::table('internal_document_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_type_id');
            $table->dropColumn('file_name_pattern');
        });

        Schema::table('account_type_requirements', function (Blueprint $table) {
            $table->dropColumn(['file_name_pattern', 'active']);
        });
    }
};
