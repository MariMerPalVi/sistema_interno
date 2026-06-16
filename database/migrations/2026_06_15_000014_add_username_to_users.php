<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 40)->nullable()->unique()->after('id');
        });

        $usernames = [
            'administrador@cooperativa.local' => 'administrador',
            'matriz@cooperativa.local' => 'matriz',
            'echeandia@cooperativa.local' => 'echeandia',
            'caluma@cooperativa.local' => 'caluma',
            'tambo@cooperativa.local' => 'tambo',
            'montalvo@cooperativa.local' => 'montalvo',
            'quinsaloma@cooperativa.local' => 'quinsaloma',
        ];

        foreach ($usernames as $email => $username) {
            DB::table('users')->where('email', $email)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
