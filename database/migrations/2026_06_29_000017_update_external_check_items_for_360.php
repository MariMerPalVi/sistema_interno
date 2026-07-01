<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('external_check_items')
            ->where('url', 'https://pjc.refla.org/refla-webapp/faces/login.xhtml')
            ->update([
                'name' => 'Coactiva',
                'updated_at' => now(),
            ]);

        DB::table('external_check_items')->updateOrInsert(
            ['url' => 'https://360.coop'],
            [
                'name' => 'Sistema 360',
                'is_required' => false,
                'active' => true,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('external_check_items')
            ->where('url', 'https://pjc.refla.org/refla-webapp/faces/login.xhtml')
            ->update([
                'name' => 'Plataforma REFLA',
                'updated_at' => now(),
            ]);

        DB::table('external_check_items')
            ->where('url', 'https://360.coop')
            ->delete();
    }
};
