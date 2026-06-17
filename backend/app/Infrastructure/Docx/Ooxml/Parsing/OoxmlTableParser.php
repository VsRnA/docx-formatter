<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\Table\OoxmlTableHtmlBuilder;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Support\Constants\HtmlCssClasses;
use DOMElement;

final class OoxmlTableParser
{
    public function __construct(
        private readonly OoxmlTableHtmlBuilder $htmlBuilder,
    ) {}

    /**
     * @return list<ParsedBlock>
     */
    public function parse(DOMElement $table, OoxmlPackage $package, ParseContext $context, int $ooxmlScopeIndex): array
    {
        $built = $this->htmlBuilder->build($table, $package, $context, HtmlCssClasses::DOC_TABLE);
        if ($built === null) {
            return [];
        }

        return [
            new ParsedBlock(
                type: BlockType::Table,
                sort: $context->nextSort(),
                html: $built['html'],
                textOriginal: $built['plain'],
                contentJson: [
                    'kind' => 'table',
                    'rows' => $built['row_count'],
                    'cols' => $built['col_count'],
                ],
                meta: OoxmlXml::filterMeta([
                    'source' => 'ooxml',
                    'ooxml_scope_index' => $ooxmlScopeIndex,
                    'ooxml_table_cells' => $built['table_cells'] !== [] ? $built['table_cells'] : null,
                    'rows' => $built['row_count'],
                    'cols' => $built['col_count'],
                    'pending_images' => $built['pending_images'] !== [] ? $built['pending_images'] : null,
                ]),
            ),
        ];
    }
}
