<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_check_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('responsible_role')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('operational_check_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_check_item_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pendiente');
            $table->string('account_number')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['account_opening_id', 'operational_check_item_id'], 'opening_operational_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_check_records');
        Schema::dropIfExists('operational_check_items');
    }
};
