<?php

namespace Tests\Feature;

use App\Models\Flat;
use Database\Factories\FlatFactory;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BladeTemplatesTest extends TestCase
{
    public function test_no_directive_is_glued_to_a_word_so_it_never_prints_as_text(): void
    {
        $glued = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'))
            ->flatMap(fn ($f) => collect(explode("\n", $f->getContents()))
                ->filter(fn ($line) => preg_match('/(?<!@)\w@(if|elseif|else|endif|foreach|endforeach|can|endcan|isset|endisset|unless|endunless|forelse|empty|endforelse)\b/', $line))
                ->keys()->map(fn ($i) => $f->getRelativePathname().':'.($i + 1)))
            ->values()->all();

        $this->assertSame([], $glued);
    }

    public function test_no_inline_php_directive_comes_before_a_php_block(): void
    {
        $mixed = collect(File::allFiles(resource_path('views')))
            // Blade pairs the first @php with the next @endphp, so an inline @php(...) before a block swallows the template.
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php') && str_contains($f->getContents(), '@endphp')
                && preg_match('/@php\s*\(/', $f->getContents(), $inline, PREG_OFFSET_CAPTURE) && preg_match('/@php(?!\s*\()/', $f->getContents(), $block, PREG_OFFSET_CAPTURE)
                && $inline[0][1] < $block[0][1])
            ->map(fn ($f) => $f->getRelativePathname())->values()->all();

        $this->assertSame([], $mixed);
    }

    public function test_project_summary_counts_units_not_yet_released(): void
    {
        $floor = FlatFactory::floorFor();
        Flat::factory()->forFloor($floor)->create(['status' => 'not_released']);
        $project = $floor->building->project;

        $this->actingAs($this->superAdmin())->get(route('projects.show', $project))->assertOk()
            ->assertSee('1 not released')->assertDontSee('available@if', false);
    }
}
