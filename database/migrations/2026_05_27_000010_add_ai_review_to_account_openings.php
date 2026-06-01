<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->string('ai_review_status')->nullable()->after('submitted_at');
            $table->unsignedTinyInteger('ai_review_score')->nullable()->after('ai_review_status');
            $table->json('ai_review_result')->nullable()->after('ai_review_score');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_review_result');
        });
    }

    public function down(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->dropColumn(['ai_review_status', 'ai_review_score', 'ai_review_result', 'ai_reviewed_at']);
        });
    }
};
