<?php

namespace Tests\Unit;

use App\Domain\Docx\Service\Support\TextRunFragmentMerger;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlRunFragmentAccumulator;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlRunTextFormatter;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlStyleResolver;
use DOMDocument;
use PHPUnit\Framework\TestCase;

final class OoxmlRunFragmentAccumulatorTest extends TestCase
{
    public function test_repeated_whitespace_run_does_not_drop_figure_parts(): void
    {
        $accumulator = new OoxmlRunFragmentAccumulator(
            new TextRunFragmentMerger,
            new OoxmlRunTextFormatter(app(OoxmlStyleResolver::class)),
        );

        $run = (new DOMDocument)->createElement('r');
        $inline = [];

        $accumulator->appendRunFragment([
            'html' => '<figure class="doc-image doc-image--inline" data-pending-marker="rId1"></figure>',
            'plain' => '',
            'inline' => [],
            'run' => $run,
        ], null, $inline);

        $accumulator->appendRunFragment([
            'html' => '<span>  </span>',
            'plain' => '  ',
            'inline' => [],
            'run' => $run,
        ], null, $inline);

        $accumulator->appendRunFragment([
            'html' => '<figure class="doc-image doc-image--inline" data-pending-marker="rId2"></figure>',
            'plain' => '',
            'inline' => [],
            'run' => $run,
        ], null, $inline);

        $accumulator->appendRunFragment([
            'html' => '<span>  </span>',
            'plain' => '  ',
            'inline' => [],
            'run' => $run,
        ], null, $inline);

        $html = implode('', $accumulator->parts());

        $this->assertSame(2, substr_count($html, '<figure'));
        $this->assertStringContainsString('rId1', $html);
        $this->assertStringContainsString('rId2', $html);
    }
}
