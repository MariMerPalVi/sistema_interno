<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('route_name')->nullable();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('notes')->nullable();
            $table->boolean('requires_spouse_docs')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('requirement_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('validation_rules')->nullable();
            $table->boolean('allows_auto_extraction')->default(false);
            $table->boolean('requires_manual_validation')->default(true);
            $table->timestamps();
        });

        Schema::create('account_type_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_type_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('external_check_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->boolean('is_required')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('internal_document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('template_path')->nullable();
            $table->boolean('requires_signature')->default(true);
            $table->boolean('is_required')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('additional_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_services');
        Schema::dropIfExists('internal_document_templates');
        Schema::dropIfExists('external_check_items');
        Schema::dropIfExists('account_type_requirements');
        Schema::dropIfExists('requirement_types');
        Schema::dropIfExists('account_types');
        Schema::dropIfExists('processes');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
