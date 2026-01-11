<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateStudyMaterialPreviews extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'study-material:generate-previews';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Generate preview images for study materials using macOS native qlmanage tool';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $materials = \App\Models\StudyMaterial::with('file')
      ->whereNotNull('file_path')
      ->whereNull('preview_path')
      ->get();

    if ($materials->isEmpty()) {
      $this->info('No study materials found needing preview generation.');
      return 0;
    }

    $this->info("Found {$materials->count()} materials to process.");

    $previewDir = 'previews';
    if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($previewDir)) {
      \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($previewDir);
    }

    $tempDir = storage_path('app/temp_previews');
    if (!file_exists($tempDir)) {
      mkdir($tempDir, 0755, true);
    }

    foreach ($materials as $material) {
      $this->line("Processing: {$material->title}");

      $filePath = storage_path('app/public/' . $material->file->file_path);

      if (!file_exists($filePath)) {
        $this->error("File not found: {$filePath}");
        continue;
      }

      // Detection and Command Execution
      $done = false;
      $generatedFile = null;  // Initialize to null

      // 1. Try macOS qlmanage
      if (!$done && shell_exec('which qlmanage')) {
        $command = 'qlmanage -t -s 1200 -o ' . escapeshellarg($tempDir) . ' ' . escapeshellarg($filePath);
        shell_exec($command);
        $generatedFile = $tempDir . '/' . basename($filePath) . '.png';
        if (file_exists($generatedFile))
          $done = true;
      }

      // 2. Try Linux tools (pdftoppm & libreoffice)
      if (!$done && shell_exec('which pdftoppm')) {
        $isPdf = str_ends_with(strtolower($filePath), '.pdf');
        $tempPdfPath = null;

        // 2.1 If it's NOT a PDF but we have LibreOffice, convert it to PDF first
        if (!$isPdf && shell_exec('which libreoffice')) {
          $this->line('Converting Office document to PDF via LibreOffice...');
          // convert-to pdf, -outdir
          $convCommand = 'libreoffice --headless --convert-to pdf --outdir ' . escapeshellarg($tempDir) . ' ' . escapeshellarg($filePath);
          shell_exec($convCommand);

          $originalBaseName = pathinfo($filePath, PATHINFO_FILENAME);
          $tempPdfPath = $tempDir . '/' . $originalBaseName . '.pdf';
        } elseif ($isPdf) {
          $tempPdfPath = $filePath;
        }

        // 2.2 If we now have a PDF (either original or converted), use pdftoppm
        if ($tempPdfPath && file_exists($tempPdfPath)) {
          $outputPrefix = $tempDir . '/' . $material->id;
          $command = 'pdftoppm -png -f 1 -l 1 -scale-to 1200 ' . escapeshellarg($tempPdfPath) . ' ' . escapeshellarg($outputPrefix);
          shell_exec($command);
          $generatedFile = $outputPrefix . '.png';

          if (file_exists($generatedFile))
            $done = true;

          // Cleanup temp PDF if it was converted
          if ($tempPdfPath !== $filePath && file_exists($tempPdfPath)) {
            unlink($tempPdfPath);
          }
        }
      }

      if ($done && isset($generatedFile) && file_exists($generatedFile)) {
        $newFileName = 'preview_' . $material->id . '_' . time() . '.png';
        $finalPath = $previewDir . '/' . $newFileName;

        // Move to public storage
        \Illuminate\Support\Facades\Storage::disk('public')->put(
          $finalPath,
          file_get_contents($generatedFile)
        );

        // Update database
        $material->update(['preview_path' => $finalPath]);

        // Cleanup temp file
        unlink($generatedFile);

        $this->info("Successfully generated preview for ID: {$material->id}");
      } else {
        $this->error("Failed to generate preview for ID: {$material->id} (No supported tool found or execution failed)");
      }
    }

    $this->info('Preview generation process completed.');
    return 0;
  }
}
