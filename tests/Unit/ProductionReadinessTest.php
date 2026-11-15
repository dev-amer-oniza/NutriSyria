<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The server runs `php artisan optimize` (cached config): from then on env() returns null
 * everywhere except inside config/*.php. Settings are read with config() instead.
 */
class ProductionReadinessTest extends TestCase
{
    public function test_env_is_read_only_in_the_config_files(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach (['app', 'routes', 'resources/views', 'database'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir")) as $file) {
                if ($file->getExtension() === 'php' && preg_match('/(?<![\w>:$])env\(/', file_get_contents($file->getPathname()))) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Read these settings with config(), defined in config/*.php.');
    }
}
