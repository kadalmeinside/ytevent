<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizer HTML berbasis DOM dengan whitelist ketat (tanpa ketergantungan eksternal).
 * Dipakai untuk konten rich-text dari panitia sebelum disimpan & dirender lewat v-html.
 */
class HtmlSanitizer
{
    /** Tag yang diizinkan (selain ini akan di-unwrap; tag berbahaya dibuang beserta isinya). */
    protected const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'a', 'span', 'div', 'hr',
    ];

    /** Tag berbahaya: dibuang total beserta seluruh isinya. */
    protected const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form',
        'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript', 'base',
    ];

    protected const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Decode entity bila klien mengirim HTML yang di-escape (&lt;p&gt;).
        if (str_contains($html, '&lt;') && str_contains($html, '&gt;')) {
            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('/<[a-z][\s\S]*>/i', $decoded)) {
                $html = $decoded;
            }
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="__root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__root__') ?? $dom->documentElement;
        if (! $root) {
            return null;
        }

        static::sanitizeChildren($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }

    protected static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            // Hapus komentar, CDATA, processing instruction.
            if ($node->nodeType === XML_COMMENT_NODE
                || $node->nodeType === XML_CDATA_SECTION_NODE
                || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);
                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }

            // Sanitasi anak lebih dulu agar unwrap membawa konten bersih.
            static::sanitizeChildren($node);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap: pertahankan teks/anak yang sudah bersih, buang pembungkusnya.
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            static::sanitizeAttributes($node, $tag);
        }
    }

    protected static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;

            if ($tag === 'a' && $name === 'href' && static::isSafeUrl($value)) {
                continue;
            }

            if ($tag === 'a' && $name === 'target' && in_array($value, ['_blank', '_self'], true)) {
                continue;
            }

            if ($name === 'style' && preg_match('/^\s*text-align\s*:\s*(left|right|center|justify)\s*;?\s*$/i', $value)) {
                continue;
            }

            $element->removeAttribute($attr->name);
        }

        if ($tag === 'a') {
            if (! $element->hasAttribute('href')) {
                $element->setAttribute('href', '#');
            }
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
            if ($element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }

    protected static function isSafeUrl(string $url): bool
    {
        // Hapus karakter kontrol/spasi yang dipakai untuk menyamarkan "java\tscript:".
        $normalized = preg_replace('/[\x00-\x20\x7f]+/', '', $url) ?? '';

        if ($normalized === '' || str_starts_with($normalized, '#') || str_starts_with($normalized, '/')) {
            return true;
        }

        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $m)) {
            return true; // URL relatif tanpa skema.
        }

        return in_array(strtolower($m[1]), self::ALLOWED_SCHEMES, true);
    }
}
