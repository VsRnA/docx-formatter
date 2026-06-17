<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

/**
 * @phpstan-type FigureGroupItem array{
 *     kind: 'image'|'callout'|'connector',
 *     left_px: int,
 *     top_px: int,
 *     width_px: int,
 *     height_px: int,
 *     html: string,
 *     marker: ?string,
 *     caption_label: ?string
 * }
 */
final class FigureGroupGeometry
{
    /**
     * @param  list<FigureGroupItem>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $bboxLeft,
        public readonly int $bboxTop,
        public readonly int $bboxWidth,
        public readonly int $bboxHeight,
    ) {}

    /**
     * @param  list<FigureGroupItem>  $items
     */
    public static function fromItems(array $items): ?self
    {
        if ($items === []) {
            return null;
        }

        $minLeft = PHP_INT_MAX;
        $minTop = PHP_INT_MAX;
        $maxRight = 0;
        $maxBottom = 0;

        foreach ($items as $item) {
            $left = $item['left_px'];
            $top = $item['top_px'];
            $width = max(0, $item['width_px']);
            $height = max(0, $item['height_px']);

            $minLeft = min($minLeft, $left);
            $minTop = min($minTop, $top);
            $maxRight = max($maxRight, $left + $width);
            $maxBottom = max($maxBottom, $top + $height);
        }

        if ($minLeft === PHP_INT_MAX) {
            return null;
        }

        return new self(
            items: $items,
            bboxLeft: $minLeft,
            bboxTop: $minTop,
            bboxWidth: max(1, $maxRight - $minLeft),
            bboxHeight: max(1, $maxBottom - $minTop),
        );
    }

    /**
     * @return list<FigureGroupItem>
     */
    public function imageItems(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (array $item): bool => $item['kind'] === 'image',
        ));
    }
}
