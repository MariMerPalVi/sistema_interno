<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_histories', function (Blueprint $table) {
            $table->string('agency')->nullable()->after('user_id')->index();
            $table->string('role')->nullable()->after('agency')->index();
            $table->string('ip_address', 45)->nullable()->after('role');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('subject_name')->nullable()->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('action_histories', function (Blueprint $table) {
            $table->dropIndex(['agency']);
            $table->dropIndex(['role']);
            $table->dropColumn([
                'agency',
                'role',
                'ip_address',
                'user_agent',
                'subject_name',
            ]);
        });
    }
};
