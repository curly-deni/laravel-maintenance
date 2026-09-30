<?php

test('package source does not call debugging helpers', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../src'));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        expect((bool) preg_match('/\b(dd|dump|ray)\s*\(/', file_get_contents($file->getPathname())))->toBeFalse();
    }
});
