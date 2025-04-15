<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanTempStorage extends Command
{
    protected $signature = 'storage:clean-temp';
    protected $description = 'Clean up temporary files older than 24 hours';

    public function handle()
    {
        $disk = Storage::disk('local');
        $tempDir = 'temp';

        if (!$disk->exists($tempDir)) {
            $this->info('No temporary directory found.');
            return;
        }

        $files = $disk->files($tempDir);
        $now = Carbon::now();
        $count = 0;

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));
            
            // Delete files older than 24 hours
            if ($now->diffInHours($lastModified) >= 24) {
                $disk->delete($file);
                $count++;
            }
        }

        $this->info("Cleaned up {$count} temporary files.");

        // Clean up empty directories
        $directories = $disk->directories($tempDir);
        foreach ($directories as $directory) {
            if (empty($disk->files($directory))) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}