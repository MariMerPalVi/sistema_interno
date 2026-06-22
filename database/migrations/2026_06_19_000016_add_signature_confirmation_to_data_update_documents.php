<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_update_documents', function (Blueprint $table) {
            $table->boolean('manual_signature_confirmed')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('data_update_documents', function (Blueprint $table) {
            $table->dropColumn('manual_signature_confirmed');
        });
    }
};
