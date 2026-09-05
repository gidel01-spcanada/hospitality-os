<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorLoggingTest extends TestCase
{
    public function test_errors_channel_writes_an_exception_to_the_daily_error_log(): void
    {
        $exception = new \RuntimeException('Error log verification');

        report($exception);

        $logPath = storage_path('logs/error-'.now()->format('Y-m-d').'.log');

        $this->assertFileExists($logPath);
        $this->assertStringContainsString('Error log verification', (string) file_get_contents($logPath));
    }
}