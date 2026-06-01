<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $combinedId = DB::table('requirement_types')->updateOrInsert(
            ['slug' => 'cedula-papeleta'],
            [
                'name' => 'Cedula y papeleta de votacion',
                'validation_rules' => 'Validar numero de cedula, nombres, apellidos, nacionalidad y datos/proceso de votacion en un mismo archivo escaneado.',
                'allows_auto_extraction' => true,
                'requires_manual_validation' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $combinedTypeId = DB::table('requirement_types')->where('slug', 'cedula-papeleta')->value('id');
        $votingTypeId = DB::table('requirement_types')->where('slug', 'papeleta-votacion')->value('id');

        $labels = [
            'cuenta-basica' => 'Cedula y papeleta de votacion',
            'cuenta-ahorro-programado' => 'Cedula y papeleta de votacion',
            'cuenta-junior' => 'Cedula y papeleta de votacion del representante',
            'cuenta-juridica' => 'Cedula y papeleta de votacion del representante legal',
        ];

        foreach ($labels as $accountSlug => $label) {
            $accountTypeId = DB::table('account_types')->where('slug', $accountSlug)->value('id');
            if (!$accountTypeId) {
                continue;
            }

            DB::table('account_type_requirements')
                ->where('account_type_id', $accountTypeId)
                ->whereIn('label', ['Original de la cedula', 'Cedula y papeleta de votacion del representante', 'Cedula y papeleta de votacion del representante legal'])
                ->update([
                    'requirement_type_id' => $combinedTypeId,
                    'label' => $label,
                    'updated_at' => now(),
                ]);

            if ($votingTypeId) {
                DB::table('account_type_requirements')
                    ->where('account_type_id', $accountTypeId)
                    ->where('requirement_type_id', $votingTypeId)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // No destructive rollback: uploaded expedientes may already reference the merged requirement.
    }
};
