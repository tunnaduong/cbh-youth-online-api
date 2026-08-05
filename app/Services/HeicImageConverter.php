<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HeicImageConverter
{
    /**
     * Returns true if this upload is HEIC/HEIF and needs conversion.
     */
    public static function isHeic(UploadedFile $file): bool
    {
        $ext = strtolower($file->getClientOriginalExtension());
        return in_array($ext, ['heic', 'heif'], true)
            || in_array($file->getMimeType(), ['image/heic', 'image/heif'], true);
    }

    /**
     * Converts an uploaded HEIC/HEIF file to JPEG and stores it on the
     * given public-disk directory. Returns [fileName, path, mimeType, size]
     * on success, or null if the conversion failed (caller should 422).
     *
     * @return array{file_name: string, path: string, mime: string, size: int}|null
     */
    public static function convertAndStore(UploadedFile $file, string $directory): ?array
    {
        $fileName = time() . '_' . uniqid() . '.jpg';
        Storage::disk('public')->makeDirectory($directory);
        $path = $directory . '/' . $fileName;
        $absPath = Storage::disk('public')->path($path);

        $cmd = sprintf(
            'convert %s -auto-orient %s 2>&1',
            escapeshellarg($file->getRealPath()),
            escapeshellarg($absPath)
        );
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($absPath)) {
            return null;
        }

        return [
            'file_name' => $fileName,
            'path' => $path,
            'mime' => 'image/jpeg',
            'size' => filesize($absPath),
        ];
    }
}
