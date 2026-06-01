<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_type_id')->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('borrador');
            $table->string('member_identification')->nullable();
            $table->string('member_first_names')->nullable();
            $table->string('member_last_names')->nullable();
            $table->string('member_nationality')->nullable();
            $table->string('member_address')->nullable();
            $table->json('extracted_data')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_data_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_opening_id')->constrained()->cascadeOnDelete();
            $table->string('template_path')->nullable();
            $table->string('signed_file_path')->nullable();
            $table->string('status')->default('pendiente');
            $table->boolean('auto_signature_detected')->default(false);
            $table->boolean('manual_signature_confirmed')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        Schema::create('uploaded_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_type_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('internal_document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_scope');
            $table->string('display_name');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('status')->default('cargado');
            $table->json('extracted_data')->nullable();
            $table->boolean('manual_signature_confirmed')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->boolean('is_manual')->default(true);
            $table->text('observations')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('external_check_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_check_item_id')->constrained()->cascadeOnDelete();
            $table->string('result')->default('pendiente');
            $table->string('screenshot_path')->nullable();
            $table->text('advisor_observation')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('selected_additional_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('additional_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['account_opening_id', 'additional_service_id'], 'opening_service_unique');
        });

        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('action_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_opening_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_histories');
        Schema::dropIfExists('observations');
        Schema::dropIfExists('selected_additional_services');
        Schema::dropIfExists('external_check_evidences');
        Schema::dropIfExists('document_validations');
        Schema::dropIfExists('uploaded_documents');
        Schema::dropIfExists('personal_data_consents');
        Schema::dropIfExists('account_openings');
    }
};
