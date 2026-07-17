<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    protected MarkdownConverter $converter;

    public function __construct()
    {
        $config = [
            'html_input' => 'strip',        // raw HTML in the source is removed, not passed through
            'allow_unsafe_links' => false,   // strips javascript:, vbscript:, data: URLs automatically
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function toHtml(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }
}
