<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);

        Route::get('/__error/{code}', fn (int $code) => abort($code));
        Route::get('/__crash', fn () => throw new \RuntimeException('boom'));
    }

    public function test_missing_page_is_branded(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('CheckMyCode')
            ->assertSee('This page doesn&#039;t exist', false)
            ->assertSee('Back to the scanner')
            ->assertSee('noindex', false);
    }

    public function test_every_error_page_renders_branded(): void
    {
        $titles = [
            401 => 'Not allowed',
            403 => 'Access denied',
            405 => 'That didn',
            413 => 'That project is too large',
            419 => 'This page expired',
            429 => 'the scan limit',
            503 => 'Back in a moment',
            418 => 'That request didn',
            502 => 'Something went wrong on our side',
        ];

        foreach ($titles as $code => $title) {
            $this->get("/__error/{$code}")
                ->assertStatus($code)
                ->assertSee('CheckMyCode')
                ->assertSee((string) $code)
                ->assertSee($title);
        }
    }

    public function test_server_errors_hide_details_and_reassure_about_files(): void
    {
        $this->get('/__crash')
            ->assertStatus(500)
            ->assertSee('Something went wrong on our side')
            ->assertSee('deleted automatically')
            ->assertDontSee('boom');
    }

    public function test_json_requests_still_get_json_errors(): void
    {
        $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
    }
}
