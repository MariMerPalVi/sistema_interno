<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('account_openings')->orderBy('id')->get()->each(function ($opening) {
            $oldFolder = $opening->storage_folder;
            $newFolder = $this->safeFileNamePart($opening->file_name);

            if ($oldFolder === $newFolder) {
                return;
            }

            DB::table('account_openings')
                ->where('id', $opening->id)
                ->update(['storage_folder' => $newFolder, 'updated_at' => now()]);

            $this->moveAndRewriteUploadedDocuments($opening->id, $oldFolder, $newFolder);
            $this->moveAndRewriteConsent($opening->id, $oldFolder, $newFolder);
            $this->moveAndRewriteExternalEvidence($opening->id, $oldFolder, $newFolder);
        });
    }

    public function down(): void
    {
        // No se reconstruye la nomenclatura anterior porque el asesor pidio conservar solo el nombre ingresado.
    }

    private function moveAndRewriteUploadedDocuments(int $openingId, string $oldFolder, string $newFolder): void
    {
        DB::table('uploaded_documents')
            ->where('account_opening_id', $openingId)
            ->whereNotNull('file_path')
            ->get()
            ->each(function ($document) use ($oldFolder, $newFolder) {
                DB::table('uploaded_documents')
                    ->where('id', $document->id)
                    ->update(['file_path' => $this->flattenPath($document->file_path, $oldFolder, $newFolder)]);
            });
    }

    private function moveAndRewriteConsent(int $openingId, string $oldFolder, string $newFolder): void
    {
        DB::table('personal_data_consents')
            ->where('account_opening_id', $openingId)
            ->whereNotNull('signed_file_path')
            ->get()
            ->each(function ($consent) use ($oldFolder, $newFolder) {
                DB::table('personal_data_consents')
                    ->where('id', $consent->id)
                    ->update(['signed_file_path' => $this->flattenPath($consent->signed_file_path, $oldFolder, $newFolder)]);
            });
    }

    private function moveAndRewriteExternalEvidence(int $openingId, string $oldFolder, string $newFolder): void
    {
        DB::table('external_check_evidences')
            ->where('account_opening_id', $openingId)
            ->whereNotNull('screenshot_path')
            ->get()
            ->each(function ($evidence) use ($oldFolder, $newFolder) {
                DB::table('external_check_evidences')
                    ->where('id', $evidence->id)
                    ->update(['screenshot_path' => $this->flattenPath($evidence->screenshot_path, $oldFolder, $newFolder)]);
            });
    }

    private function flattenPath(string $path, string $oldFolder, string $newFolder): string
    {
        $fileName = basename(str_replace('\\', '/', $path));
        $newPath = "aperturas/{$newFolder}/{$fileName}";

        if ($path !== $newPath && Storage::exists($path) && !Storage::exists($newPath)) {
            Storage::makeDirectory("aperturas/{$newFolder}");
            Storage::move($path, $newPath);
        }

        return $newPath;
    }

    private function safeFileNamePart(?string $value): string
    {
        $value = $value ?: 'expediente';
        $value = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return trim($value, '. ') ?: 'expediente';
    }
};
