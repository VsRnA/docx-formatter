<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlAnchorLayoutParser;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlAnchorShapeRenderer;
use PHPUnit\Framework\TestCase;

final class OoxmlAnchorShapeRendererTest extends TestCase
{
    public function test_renders_straight_connector_with_flip_and_color(): void
    {
        $xml = <<<'XML'
<w:drawing xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
  <wp:anchor>
    <wp:positionH relativeFrom="column"><wp:posOffset>2381250</wp:posOffset></wp:positionH>
    <wp:positionV relativeFrom="paragraph"><wp:posOffset>889635</wp:posOffset></wp:positionV>
    <wp:extent cx="2032635" cy="336550"/>
    <a:graphic>
      <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
        <wps:wsp>
          <wps:spPr>
            <a:xfrm flipV="1">
              <a:ext cx="2032635" cy="336550"/>
            </a:xfrm>
            <a:prstGeom prst="straightConnector1"/>
            <a:ln w="9525">
              <a:solidFill><a:srgbClr val="FF0000"/></a:solidFill>
              <a:tailEnd type="triangle"/>
            </a:ln>
          </wps:spPr>
        </wps:wsp>
      </a:graphicData>
    </a:graphic>
  </wp:anchor>
</w:drawing>
XML;

        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadXML($xml);
        $scope = $document->documentElement;
        $this->assertInstanceOf(\DOMElement::class, $scope);

        $renderer = new OoxmlAnchorShapeRenderer(new OoxmlAnchorLayoutParser);
        $html = $renderer->renderFromScope($scope);

        $this->assertStringContainsString('doc-anchor-shape--straightConnector1', $html);
        $this->assertStringContainsString('stroke="#FF0000"', $html);
        $this->assertStringContainsString('marker-end="url(#doc-arrow-triangle)"', $html);
        $this->assertStringContainsString('y1="35"', $html);
        $this->assertStringContainsString('y2="0"', $html);
    }
}
