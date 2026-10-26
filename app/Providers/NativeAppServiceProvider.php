<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Menu::create(
            Menu::app(),
            Menu::make(
                Menu::link('/', 'New Scan'),
                Menu::separator(),
                Menu::link(config('checkmycode.github_url'), 'Author on GitHub')
                    ->openInBrowser(),
                Menu::link(config('checkmycode.sponsor_url'), 'Sponsor CheckMyCode')
                    ->openInBrowser(),
            )->label('Scan'),
            Menu::edit(),
            Menu::view(),
            Menu::window(),
        );

        Window::open()
            ->width(1100)
            ->height(760)
            ->minWidth(900)
            ->minHeight(600)
            ->backgroundColor('#0f172a')
            ->rememberState()
            ->titleBarHidden()
            ->title('CheckMyCode');
    }

    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'max_execution_time' => '0',
        ];
    }
}
