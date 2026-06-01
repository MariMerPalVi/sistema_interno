<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->string('public_code', 20)->nullable()->unique()->after('id');
            $table->string('file_name')->nullable()->after('public_code');
            $table->string('storage_folder')->nullable()->after('file_name');
        });

        DB::table('account_openings')->orderBy('id')->get()->each(function ($opening) {
            $code = $this->makeCode();
            while (DB::table('account_openings')->where('public_code', $code)->exists()) {
                $code = $this->makeCode();
            }

            $name = $opening->member_identification
                ? 'expediente-'.$opening->member_identification
                : 'expediente-'.$code;

            DB::table('account_openings')
                ->where('id', $opening->id)
                ->update([
                    'public_code' => $code,
                    'file_name' => $name,
                    'storage_folder' => $code.'-'.Str::slug($name),
                    'updated_at' => now(),
                ]);
        });

        Schema::table('account_openings', function (Blueprint $table) {
            $table->string('public_code', 20)->nullable(false)->change();
            $table->string('file_name')->nullable(false)->change();
            $table->string('storage_folder')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('account_openings', function (Blueprint $table) {
            $table->dropUnique(['public_code']);
            $table->dropColumn(['public_code', 'file_name', 'storage_folder']);
        });
    }

    private function makeCode(): string
    {
        return 'AP-'.now()->format('ym').'-'.random_int(100000, 999999);
    }
};
