<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('account_openings', 'account_openings_agency_status_created_at_idx', ['agency', 'status', 'created_at']);
        $this->addIndexIfMissing('account_openings', 'account_openings_type_status_idx', ['account_type_id', 'status']);
        $this->addIndexIfMissing('uploaded_documents', 'uploaded_documents_opening_scope_status_idx', ['account_opening_id', 'document_scope', 'status']);
        $this->addIndexIfMissing('personal_data_consents', 'personal_data_consents_opening_status_idx', ['account_opening_id', 'status']);
        $this->addIndexIfMissing('external_check_evidence', 'external_check_evidence_opening_subject_status_idx', ['account_opening_id', 'subject', 'status']);
        $this->addIndexIfMissing('action_histories', 'action_histories_action_created_at_idx', ['action', 'created_at']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('action_histories', 'action_histories_action_created_at_idx');
        $this->dropIndexIfExists('external_check_evidence', 'external_check_evidence_opening_subject_status_idx');
        $this->dropIndexIfExists('personal_data_consents', 'personal_data_consents_opening_status_idx');
        $this->dropIndexIfExists('uploaded_documents', 'uploaded_documents_opening_scope_status_idx');
        $this->dropIndexIfExists('account_openings', 'account_openings_type_status_idx');
        $this->dropIndexIfExists('account_openings', 'account_openings_agency_status_created_at_idx');
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, fn (Blueprint $table) => $table->index($columns, $indexName));
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, fn (Blueprint $table) => $table->dropIndex($indexName));
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return collect(DB::select(
            'select index_name from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
            [$database, $table, $indexName]
        ))->isNotEmpty();
    }
};
