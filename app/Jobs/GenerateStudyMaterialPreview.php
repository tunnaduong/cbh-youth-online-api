<?php

namespace App\Jobs;

use App\Models\StudyMaterial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateStudyMaterialPreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        private int $materialId,
    ) {}

    public function handle(): void
    {
        $material = StudyMaterial::with('file')->find($this->materialId);

        if (!$material) {
            Log::error('GenerateStudyMaterialPreview: material not found', ['id' => $this->materialId]);
            return;
        }

        if (!$material->file) {
            Log::error('GenerateStudyMaterialPreview: file not found for material', ['id' => $this->materialId]);
            return;
        }

        $filePath = storage_path('app/public/' . $material->file->file_path);

        if (!file_exists($filePath)) {
            Log::error('GenerateStudyMaterialPreview: file not found on disk', [
                'id'        => $this->materialId,
                'file_path' => $filePath,
            ]);
            return;
        }

        $done = false;
        $generatedFile = null;
        $tempDir = storage_path('app/temp_previews');

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 1. Try macOS qlmanage
        if (!$done && shell_exec('command -v qlmanage')) {
            $command = 'qlmanage -t -s 1200 -o ' . escapeshellarg($tempDir) . ' ' . escapeshellarg($filePath);
            shell_exec($command);
            $generatedFile = $tempDir . '/' . basename($filePath) . '.png';
            if (file_exists($generatedFile)) {
                $done = true;
            }
        }

        // 2. Try Linux tools (pdftoppm & libreoffice)
        if (!$done && shell_exec('command -v pdftoppm')) {
            $isPdf = str_ends_with(strtolower($filePath), '.pdf');
            $tempPdfPath = null;

            if (!$isPdf && shell_exec('command -v libreoffice')) {
                $convCommand = 'libreoffice --headless --convert-to pdf --outdir ' . escapeshellarg($tempDir) . ' ' . escapeshellarg($filePath);
                shell_exec($convCommand);

                $originalBaseName = pathinfo($filePath, PATHINFO_FILENAME);
                $tempPdfPath = $tempDir . '/' . $originalBaseName . '.pdf';
            } elseif ($isPdf) {
                $tempPdfPath = $filePath;
            }

            if ($tempPdfPath && file_exists($tempPdfPath)) {
                $outputPrefix = $tempDir . '/' . $material->id;
                $command = 'pdftoppm -singlefile -png -f 1 -l 1 -scale-to 1200 ' . escapeshellarg($tempPdfPath) . ' ' . escapeshellarg($outputPrefix) . ' 2>&1';
                $output = shell_exec($command);
                $generatedFile = $outputPrefix . '.png';

                if (file_exists($generatedFile)) {
                    $done = true;
                } else {
                    Log::error('GenerateStudyMaterialPreview: pdftoppm failed', [
                        'id'     => $this->materialId,
                        'output' => $output,
                    ]);
                }

                if ($tempPdfPath !== $filePath && file_exists($tempPdfPath)) {
                    unlink($tempPdfPath);
                }
            }
        }

        if ($done && $generatedFile && file_exists($generatedFile)) {
            $previewDir = 'previews';
            if (!Storage::disk('public')->exists($previewDir)) {
                Storage::disk('public')->makeDirectory($previewDir);
            }

            $newFileName = 'preview_' . $material->id . '_' . time() . '.png';
            $finalPath = $previewDir . '/' . $newFileName;

            Storage::disk('public')->put(
                $finalPath,
                file_get_contents($generatedFile)
            );

            $material->update(['preview_path' => $finalPath]);
            unlink($generatedFile);

            Log::info('GenerateStudyMaterialPreview: completed', [
                'id'            => $this->materialId,
                'preview_path'  => $finalPath,
            ]);
        } else {
            Log::error('GenerateStudyMaterialPreview: failed to generate', [
                'id' => $this->materialId,
            ]);
        }
    }
}
