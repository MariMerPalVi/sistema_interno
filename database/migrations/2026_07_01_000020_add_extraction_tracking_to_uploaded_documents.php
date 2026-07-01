<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('uploaded_documents', 'extraction_source')) {
                $table->string('extraction_source')->nullable()->after('extracted_data')->index();
            }

            if (!Schema::hasColumn('uploaded_documents', 'extraction_confidence')) {
                $table->unsignedTinyInteger('extraction_confidence')->nullable()->after('extraction_source');
            }

            if (!Schema::hasColumn('uploaded_documents', 'requires_manual_data_review')) {
                $table->boolean('requires_manual_data_review')->default(false)->after('extraction_confidence')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_documents', function (Blueprint $table) {
            if (Schema::hasColumn('uploaded_documents', 'requires_manual_data_review')) {
                $table->dropColumn('requires_manual_data_review');
            }

            if (Schema::hasColumn('uploaded_documents', 'extraction_confidence')) {
                $table->dropColumn('extraction_confidence');
            }

            if (Schema::hasColumn('uploaded_documents', 'extraction_source')) {
                $table->dropColumn('extraction_source');
            }
        });
    }
};
