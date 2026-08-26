<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetPlatformMode extends Command
{
    protected $signature = 'platform:mode {mode? : on_premise or cloud}';

    protected $description = 'Show or switch PLATFORM_MODE in .env for local on-premise/cloud testing';

    public function handle(): int
    {
        $mode = $this->argument('mode');

        if (! $mode) {
            $this->info('Current mode: ' . config('platform.mode'));
            $this->line('Usage: php artisan platform:mode on_premise|cloud');

            return self::SUCCESS;
        }

        if (! in_array($mode, ['on_premise', 'cloud'], true)) {
            $this->error("Invalid mode '{$mode}'. Use 'on_premise' or 'cloud'.");

            return self::FAILURE;
        }

        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->error('.env not found at ' . $envPath);

            return self::FAILURE;
        }

        $env = File::get($envPath);
        $line = "PLATFORM_MODE={$mode}";

        $env = preg_match('/^PLATFORM_MODE=.*/m', $env)
            ? preg_replace('/^PLATFORM_MODE=.*/m', $line, $env)
            : rtrim($env) . PHP_EOL . $line . PHP_EOL;

        File::put($envPath, $env);
        $this->call('config:clear');

        $this->info("PLATFORM_MODE set to '{$mode}'.");

        if ($mode === 'cloud') {
            $this->line('Public pages will now show "Hospitality OS" branding regardless of any tenant\'s own settings.');
        } else {
            $this->line('Public pages will now show the operator\'s own brand (admin Settings > Site name).');
        }

        return self::SUCCESS;
    }
}
