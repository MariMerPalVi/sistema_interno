<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('active');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('password_changed_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'must_change_password',
                'password_changed_at',
                'failed_login_attempts',
                'locked_until',
                'last_login_at',
            ]);
        });
    }
};
