<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;




class ClearLoginSession extends Command
{
    protected $signature = 'clear:login-session';

    protected $description = 'Clear web login sessions';

    public function handle()
    {
        try {
            $sessionPath = storage_path('framework/sessions');

            if (File::exists($sessionPath)) {
                foreach (File::files($sessionPath) as $file) {
                    File::delete($file->getPathname());
                }
            }

             // Mark all existing web sessions as invalid
            Cache::forever(
                'web_sessions_invalidated_at',
                now()->timestamp
            );

            $this->info('All web sessions have been cleared.');
            $this->info('All web users are now logged out.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to clear web sessions: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}