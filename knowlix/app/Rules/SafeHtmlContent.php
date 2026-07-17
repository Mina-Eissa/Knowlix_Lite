<?php

namespace App\Rules;

use App\Services\MarkdownRenderer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeHtmlContent implements ValidationRule
{
    public function __construct(protected MarkdownRenderer $renderer = new MarkdownRenderer()) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
{
    if (! is_string($value)) {
        return;
    }

    if (preg_match('/<[a-z!\/][^>]*>/i', $value)) {
        $fail("The {$attribute} must not contain raw HTML — use Markdown formatting instead.");
        return;
    }

    // Check the RAW source for dangerous URL schemes in Markdown link syntax —
    // this must happen before rendering, since CommonMark neutralizes these
    // during conversion, which would otherwise hide the attempt from us entirely.
    if (preg_match_all('/!?\[[^\]]*\]\(\s*([^)\s]+)/', $value, $matches)) {
        foreach ($matches[1] as $url) {
            $decoded = html_entity_decode(rawurldecode($url));
            $scheme = strtolower(trim(explode(':', $decoded)[0] ?? ''));

            if (in_array($scheme, ['javascript', 'vbscript', 'data'], true)) {
                $fail("The {$attribute} contains a link with a disallowed URL scheme.");
                return;
            }
        }
    }

    try {
        $html = $this->renderer->toHtml($value);
    } catch (\Throwable $e) {
        $fail("The {$attribute} could not be processed as valid Markdown.");
        return;
    }

    if (preg_match('/<script|javascript\s*:|on\w+\s*=/i', $html)) {
        $fail("The {$attribute} contains disallowed content after rendering.");
    }
}
}
