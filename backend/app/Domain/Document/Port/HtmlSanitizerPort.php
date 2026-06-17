<?php

namespace App\Domain\Document\Port;

interface HtmlSanitizerPort
{
    public function sanitize(string $html): string;
}
