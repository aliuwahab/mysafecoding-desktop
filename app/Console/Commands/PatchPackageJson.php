<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Native\Desktop\Drivers\Electron\ElectronServiceProvider;

class PatchPackageJson extends Command
{
    protected $signature = 'native:patch-package-json';

    protected $description = 'Add repository field to Electron package.json for GitHub Releases publishing';

    public function handle(): int
    {
        $packageJsonPath = ElectronServiceProvider::electronPath('package.json');

        if (! file_exists($packageJsonPath)) {
            $this->error('Electron package.json not found.');
            return self::FAILURE;
        }

        $packageJson = json_decode(file_get_contents($packageJsonPath), true);

        $packageJson['repository'] = [
            'type' => 'git',
            'url' => 'https://github.com/' . config('nativephp.updater.providers.github.owner') . '/' . config('nativephp.updater.providers.github.repo') . '.git',
        ];

        file_put_contents($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Patched package.json with repository: ' . $packageJson['repository']['url']);

        // Patch electron-builder.mjs to use zip target instead of dmg
        // hdiutil fails on Apple Silicon when converting APFS to UDZO format
        $builderPath = ElectronServiceProvider::electronPath('electron-builder.mjs');

        if (file_exists($builderPath)) {
            $content = file_get_contents($builderPath);

            if (! str_contains($content, "target: ['zip']")) {
                $content = str_replace(
                    "mac: {\n        entitlementsInherit: 'build/entitlements.mac.plist',",
                    "mac: {\n        target: ['zip'],\n        entitlementsInherit: 'build/entitlements.mac.plist',",
                    $content
                );

                file_put_contents($builderPath, $content);
                $this->info('Patched electron-builder.mjs: mac target set to zip only (skipping dmg).');
            }
        }

        return self::SUCCESS;
    }
}
