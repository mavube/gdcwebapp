<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Normalises user-supplied demographic text before it is persisted.
 *
 * These columns are plain text, so the goal is not "safe HTML" but "no HTML at
 * all". The Symfony sanitizer alone is not sufficient for that: it neutralises
 * scripts and dangerous attributes, but re-serialises the elements it considers
 * harmless. Feeding it "<b>Acme</b>" returns the markup intact and
 * "<img src=x onerror=alert(1)>" returns "<img />", both of which are wrong for
 * a VARCHAR(150) company name.
 *
 * The order below therefore matters:
 *   1. Sanitize, to neutralise scripts and dangerous attributes.
 *   2. strip_tags, to remove every remaining tag the sanitizer kept.
 *   3. Decode entities, so "&amp;" becomes "&" in the stored plain text.
 *   4. Strip control characters and collapse whitespace.
 *
 * Steps 2 and 3 must stay in this order. Decoding before stripping would turn
 * an escaped "&lt;script&gt;" back into a live tag for the stripping step to
 * miss, and decoding last is safe because the value is now tag-free text.
 *
 * Step 1 depends on the "gdc.text" sanitizer keeping allow_safe_elements on. See
 * config/packages/html_sanitizer.yaml: without it the sanitizer drops the text
 * inside unwrapped tags as well as the tags themselves.
 */
final class InquirySanitizer
{
    public function __construct(
        private readonly HtmlSanitizerInterface $textSanitizer,
    ) {
    }

    /** Single-line fields: collapses all whitespace, including newlines. */
    public function clean(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = $this->toPlainText($value);
        $text = preg_replace('/\s+/u', " ", $text) ?? "";

        return mb_substr(trim($text), 0, $maxLength);
    }

    /** Multi-line fields: preserves paragraph breaks, still tag-free. */
    public function cleanMultiline(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = $this->toPlainText($value);
        $text = preg_replace('/[ \t]+/u', " ", $text) ?? "";
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? "";

        return mb_substr(trim($text), 0, $maxLength);
    }

    /**
     * Produces tag-free plain text. Exposed separately so tests can assert the
     * guarantee directly rather than only through the column limits.
     */
    public function toPlainText(string $value): string
    {
        $sanitized = $this->textSanitizer->sanitize($value);
        $stripped = strip_tags($sanitized);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, "UTF-8");

        // Decoding can reconstitute a tag from its escaped form: the input
        // "&lt;script&gt;alert(1)&lt;/script&gt;" survives sanitizing and
        // strip_tags as text, then decodes to a live <script> element. A
        // second strip closes that gap.
        $decoded = strip_tags($decoded);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', "", $decoded) ?? "";
    }
}
