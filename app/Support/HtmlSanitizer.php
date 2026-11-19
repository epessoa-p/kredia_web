<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Limpia HTML de plantillas/contratos (editor WYSIWYG) antes de guardarlo o renderizarlo
 * con {!! !!}. Conserva formato (tablas, estilos, imágenes, placeholders {{...}}) y elimina
 * todo lo que pueda ejecutar código: <script>, <iframe>, atributos on*, URLs javascript:, etc.
 */
final class HtmlSanitizer
{
    /** Etiquetas que se eliminan junto con su contenido. */
    private const DROP_WITH_CONTENT = [
        'script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base',
        'svg', 'math', 'template', 'noscript', 'audio', 'video', 'source', 'portal',
    ];

    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'background', 'poster', 'xlink:href', 'srcset'];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        // Envoltorio para conservar fragmentos y forzar UTF-8.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__kredia_root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);

        foreach (self::DROP_WITH_CONTENT as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Comentarios condicionales / ocultos.
        foreach (iterator_to_array($xpath->query('//comment()')) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            /** @var DOMElement $element */
            if (strtolower($element->nodeName) === 'style') {
                $element->textContent = self::cleanCss($element->textContent);
                continue;
            }
            self::cleanAttributes($element);
        }

        $root = $doc->getElementById('__kredia_root__') ?? $xpath->query('//div[@id="__kredia_root__"]')->item(0);
        if (!$root) {
            return '';
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = (string) $attr->nodeValue;

            if (str_starts_with($name, 'on') || $name === 'formaction' || $name === 'srcdoc') {
                $element->removeAttribute($attr->nodeName);
                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && !self::isSafeUrl($value, $name === 'src')) {
                $element->removeAttribute($attr->nodeName);
                continue;
            }

            if ($name === 'style') {
                $element->setAttribute('style', self::cleanCss($value));
            }
        }
    }

    private static function isSafeUrl(string $url, bool $allowImageData): bool
    {
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:')) {
            return false;
        }

        if (str_starts_with($normalized, 'data:')) {
            return $allowImageData && preg_match('#^data:image/(png|jpe?g|gif|webp|bmp);base64,#', $normalized) === 1;
        }

        return true;
    }

    private static function cleanCss(string $css): string
    {
        $patterns = [
            '/expression\s*\(/i',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/@import/i',
            '/behavior\s*:/i',
            '/-moz-binding/i',
        ];

        return preg_replace($patterns, '', $css) ?? '';
    }
}
