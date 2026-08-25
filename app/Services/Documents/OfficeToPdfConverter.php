<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class OfficeToPdfConverter
{
    public function convert(string $documentPath, ?string $outputDirectory = null): string
    {
        if (! is_file($documentPath)) {
            throw new RuntimeException('Das zu konvertierende Word-Dokument wurde nicht gefunden.');
        }

        $outputDirectory ??= dirname($documentPath);
        File::ensureDirectoryExists($outputDirectory);

        $profileDirectory = $outputDirectory . DIRECTORY_SEPARATOR . 'lo-profile-' . Str::uuid();
        File::ensureDirectoryExists($profileDirectory);

        $outputPath = $outputDirectory . DIRECTORY_SEPARATOR
            . pathinfo($documentPath, PATHINFO_FILENAME) . '.pdf';

        if (is_file($outputPath)) {
            File::delete($outputPath);
        }

        try {
            $process = new Process([
                $this->binary(),
                '-env:UserInstallation=' . $this->fileUri($profileDirectory),
                '--headless',
                '--norestore',
                '--nolockcheck',
                '--nodefault',
                '--nofirststartwizard',
                '--convert-to',
                'pdf',
                '--outdir',
                $outputDirectory,
                $documentPath,
            ]);
            $process->setTimeout((float) config('services.libreoffice.timeout', 120));
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                $details = trim($process->getErrorOutput() ?: $process->getOutput());
                throw new RuntimeException(
                    'Die originalgetreue Word-PDF-Konvertierung ist fehlgeschlagen.'
                    . ($details !== '' ? ' ' . $details : '')
                );
            }

            return $outputPath;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'LibreOffice konnte für die originalgetreue PDF-Erstellung nicht gestartet werden.',
                previous: $exception
            );
        } finally {
            File::deleteDirectory($profileDirectory);
        }
    }

    private function binary(): string
    {
        $configured = trim((string) config('services.libreoffice.binary'));
        if ($configured !== '') {
            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            foreach ([
                'C:\\Program Files\\LibreOffice\\program\\soffice.com',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',
            ] as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            return 'soffice.com';
        }

        return 'libreoffice';
    }

    private function fileUri(string $path): string
    {
        $normalized = str_replace('\\', '/', realpath($path) ?: $path);
        $segments = explode('/', $normalized);

        if (preg_match('/^[A-Za-z]:$/', $segments[0] ?? '') === 1) {
            $drive = array_shift($segments);

            return 'file:///' . $drive . '/' . implode('/', array_map('rawurlencode', $segments));
        }

        return 'file://' . implode('/', array_map('rawurlencode', $segments));
    }
}
