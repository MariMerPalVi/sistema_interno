<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_update_processes', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 20)->unique();
            $table->string('file_name')->unique();
            $table->string('storage_folder');
            $table->string('agency', 60)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('borrador');
            $table->string('member_identification', 20);
            $table->string('member_name')->nullable();
            $table->json('selected_changes')->nullable();
            $table->json('current_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_update_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_update_process_id')->constrained()->cascadeOnDelete();
            $table->string('document_key');
            $table->string('display_name');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('status')->default('cargado');
            $table->text('observations')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['data_update_process_id', 'document_key'], 'data_update_document_unique');
        });

        Schema::create('data_update_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_update_process_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_update_histories');
        Schema::dropIfExists('data_update_documents');
        Schema::dropIfExists('data_update_processes');
    }
};
