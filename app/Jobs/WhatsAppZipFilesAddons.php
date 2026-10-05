<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ZipArchive;
use Illuminate\Support\Facades\File;

/**
 * Unlike Franchise/Agent/Dispatcher (plain classes dropped into already-autoloaded app/
 * namespaces), the WhatsApp addon is a self-contained module with its own PSR-4 namespace
 * (Modules\WhatsApp\ -> modules/WhatsApp/) and a handful of Inertia page shims that must live
 * outside the module folder (resources/js/Pages/whatsapp/*.vue -> import from the module).
 * The zip is expected to contain exactly two top-level folders carrying those two destinations.
 *
 * This job only places files — it deliberately does NOT flip the `whatsapp-addon` settings
 * flag itself. `php artisan whatsapp:install` (run manually after upload, per the on-page
 * instructions) idempotently seeds that setting row with its proper field/category metadata;
 * writing a bare {name, value} row here first would leave it malformed until install runs, and
 * running install afterwards would just reset the flag to its seeded default anyway.
 */
class WhatsAppZipFilesAddons implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $zipFileName;
    protected $zipFilePath;

    public function __construct($zipFileName, $zipFilePath)
    {
        $this->zipFileName = $zipFileName;
        $this->zipFilePath = $zipFilePath;
    }

    public function handle(): void
    {
        $uploadPath = storage_path('app/public/');
        $zip = new ZipArchive;

        if ($zip->open($this->zipFilePath) !== TRUE) {
            File::delete($this->zipFilePath);
            return;
        }

        $tempExtractPath = $uploadPath . '/whatsapp-extracted';
        if (!file_exists($tempExtractPath)) {
            mkdir($tempExtractPath, 0777, true);
        }

        $zip->extractTo($tempExtractPath);
        $zip->close();

        // ----------------------------------
        // CHECK REQUIRED TOP-LEVEL FOLDERS
        // ----------------------------------
        $destinations = [
            'WhatsApp' => base_path('modules/WhatsApp'),
            'whatsapp-pages' => resource_path('js/Pages/whatsapp'),
        ];

        $extractedDirs = array_filter(glob($tempExtractPath . '/*'), 'is_dir');
        $foundFolders = array_map('basename', $extractedDirs);
        $missing = array_diff(array_keys($destinations), $foundFolders);

        if (!empty($missing)) {
            File::deleteDirectory($tempExtractPath);
            File::delete($this->zipFilePath);
            return;
        }

        // Sanity check: the WhatsApp folder must actually be the module root (not, say, a
        // nested modules/WhatsApp/modules/WhatsApp mistake), so require its provider file.
        if (!file_exists($tempExtractPath . '/WhatsApp/WhatsAppServiceProvider.php')) {
            File::deleteDirectory($tempExtractPath);
            File::delete($this->zipFilePath);
            return;
        }

        foreach ($destinations as $folder => $target) {
            $source = $tempExtractPath . '/' . $folder;
            File::ensureDirectoryExists($target);
            File::copyDirectory($source, $target);
        }

        // Clean up temp files
        File::deleteDirectory($tempExtractPath);
        File::delete($this->zipFilePath);
    }
}
