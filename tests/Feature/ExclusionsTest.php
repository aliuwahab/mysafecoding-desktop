<?php

namespace Tests\Feature;

use App\Livewire\Scanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExclusionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_include_dependency_and_build_folders(): void
    {
        Livewire::test(Scanner::class)
            ->assertSet('exclusions', config('checkmycode.default_exclusions'))
            ->assertSee('Exclude');

        $this->assertContains('deps', config('checkmycode.default_exclusions'));
        $this->assertContains('_build', config('checkmycode.default_exclusions'));
    }

    public function test_several_folders_can_be_added_at_once_and_bad_paths_are_ignored(): void
    {
        $component = Livewire::test(Scanner::class)
            ->call('resetExclusions')
            ->set('newExclusion', 'priv/static, tmp  /docs/ ../etc vendor')
            ->call('addExclusion')
            ->assertSet('newExclusion', '')
            ->assertDispatched('exclusions-changed');

        $exclusions = $component->get('exclusions');
        $this->assertContains('priv/static', $exclusions);
        $this->assertContains('tmp', $exclusions);
        $this->assertContains('docs', $exclusions);
        $this->assertNotContains('../etc', $exclusions);
        $this->assertSame(1, count(array_keys($exclusions, 'vendor')));
    }

    public function test_a_suggestion_or_largest_folder_can_be_added_directly(): void
    {
        Livewire::test(Scanner::class)
            ->call('addExclusion', 'assets/videos')
            ->assertSee('assets/videos');
    }

    public function test_changes_are_remembered_and_can_be_reset(): void
    {
        Livewire::test(Scanner::class)
            ->call('addExclusion', 'legacy')
            ->call('removeExclusion', 0);

        $remembered = session('checkmycode.exclusions');
        $this->assertContains('legacy', $remembered);
        $this->assertNotContains(config('checkmycode.default_exclusions')[0], $remembered);

        Livewire::test(Scanner::class)
            ->assertSet('exclusions', $remembered)
            ->call('resetExclusions')
            ->assertSet('exclusions', config('checkmycode.default_exclusions'));

        $this->assertNull(session('checkmycode.exclusions'));
    }
}
