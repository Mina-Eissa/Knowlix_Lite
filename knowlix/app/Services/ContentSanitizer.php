<?php

namespace App\Services;

class ContentSanitizer
{
    /** Tags allowed to remain in rich-text fields (article/ticket bodies) */
    protected array $allowedTags = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'br', 'blockquote'];

    /** Per-tag attributes allowed to remain */
    protected array $allowedAttributes = [
        'a' => ['href'],
    ];

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument();

        // Suppress warnings from malformed HTML fragments; we only want the parse tree.
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        $this->sanitizeNode($dom, $wrapper);

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return trim($result);
    }

    protected function sanitizeNode(\DOMDocument $dom, \DOMNode $node): void
    {
        // Walk a static list of children — the live list mutates as we remove nodes below.
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue; // plain text is always safe, leave it as-is
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (! in_array($tag, $this->allowedTags, true)) {
                // Disallowed tag: drop the tag itself, but keep its text content
                // e.g. <script>alert(1)</script> -> removed entirely (see below)
                if ($tag === 'script' || $tag === 'style') {
                    $node->removeChild($child); // strip dangerous content entirely, don't keep text
                } else {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
                continue;
            }

            $this->stripDisallowedAttributes($child, $tag);
            $this->sanitizeNode($dom, $child); // recurse into children
        }
    }

    protected function stripDisallowedAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = $this->allowedAttributes[$tag] ?? [];
        $attributesToRemove = [];

        foreach ($element->attributes as $attr) {
            $name = strtolower($attr->name);

            if (! in_array($name, $allowed, true)) {
                $attributesToRemove[] = $attr->name;
                continue;
            }

            if ($name === 'href' && $this->isDangerousUrl($attr->value)) {
                $attributesToRemove[] = $attr->name;
            }
        }

        foreach ($attributesToRemove as $name) {
            $element->removeAttribute($name);
        }
    }

    protected function isDangerousUrl(string $url): bool
    {
        $url = trim(strtolower($url));

        return str_starts_with($url, 'javascript:')
            || str_starts_with($url, 'data:')
            || str_starts_with($url, 'vbscript:');
    }
}
