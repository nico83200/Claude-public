<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Descriptions mises en forme (produits, pages).
 * - Texte brut : retours à la ligne, paragraphes, **gras**, *italique*, listes « - » ou « 1. ».
 * - HTML (éditeur de l'admin, copier-coller Word…) : nettoyé, seules les balises de mise en forme sont gardées.
 */
final class RichText
{
    private const TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
        'a' => ['href', 'target', 'rel'], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'img' => ['src', 'alt', 'width', 'height'],
        'span' => [], 'div' => [],
    ];
    /** Balises supprimées avec leur contenu. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'head', 'title', 'meta', 'link', 'svg', 'math', 'template', 'noscript'];

    public static function isHtml(string $text): bool
    {
        return (bool) preg_match('#<(p|br|ul|ol|li|h[1-6]|div|table|strong|b|em|i|u|a|blockquote|span)\b[^>]*>#i', $text);
    }

    /** HTML prêt à afficher. */
    public static function render(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        return self::isHtml($text) ? self::sanitize($text) : self::fromPlain($text);
    }

    /** Texte brut → HTML (échappé). */
    public static function fromPlain(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $html = [];
        foreach (preg_split("/\n{2,}/", $text) as $block) {
            $lines = explode("\n", trim($block));
            $bullets = array_filter($lines, static fn ($l) => preg_match('/^\s*[-•*]\s+/u', $l));
            $numbers = array_filter($lines, static fn ($l) => preg_match('/^\s*\d+[.)]\s+/', $l));
            if (count($bullets) === count($lines)) {
                $html[] = '<ul>' . implode('', array_map(static fn ($l) => '<li>' . self::inline(preg_replace('/^\s*[-•*]\s+/u', '', $l)) . '</li>', $lines)) . '</ul>';
            } elseif (count($numbers) === count($lines)) {
                $html[] = '<ol>' . implode('', array_map(static fn ($l) => '<li>' . self::inline(preg_replace('/^\s*\d+[.)]\s+/', '', $l)) . '</li>', $lines)) . '</ol>';
            } elseif (count($lines) === 1 && preg_match('/^#{2,3}\s+(.+)$/u', $lines[0], $m)) {
                $html[] = '<h3>' . self::inline($m[1]) . '</h3>';
            } else {
                $html[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
            }
        }
        return implode("\n", $html);
    }

    private static function inline(string $line): string
    {
        $line = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $line = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $line);
        $line = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $line);
        $line = preg_replace('/(?<!\w)_(?!\s)(.+?)(?<!\s)_(?!\w)/u', '<em>$1</em>', $line);
        return $line;
    }

    /** Nettoie un HTML : balises de mise en forme uniquement, sans styles, classes ni scripts. */
    public static function sanitize(string $html): string
    {
        if (!class_exists(\DOMDocument::class)) {
            return strip_tags($html, '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><a><blockquote><table><thead><tbody><tr><th><td>');
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="rich-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('rich-root') ?? $doc->documentElement;
        if (!$root) {
            return '';
        }
        self::clean($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        // Paragraphes vides laissés par les éditeurs / Word
        $out = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#u', '', $out);
        return trim((string) $out);
    }

    private static function clean(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::DROP, true) || str_contains($tag, ':')) {
                $node->removeChild($child);
                continue;
            }
            self::clean($child);
            if (!isset(self::TAGS[$tag])) {
                // Balise inconnue : on garde son texte
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);
                $value = trim($attr->nodeValue ?? '');
                $allowed = in_array($name, self::TAGS[$tag], true)
                    || ($name === 'class' && preg_match('/^(lead|muted|small|center)( (lead|muted|small|center))*$/', $value));
                if ($allowed && in_array($name, ['href', 'src'], true) && !preg_match('#^(https?://|/|mailto:|tel:|\#|\{\{|[a-z0-9][\w./-]*$)#i', $value)) {
                    $allowed = false; // javascript:, data:…
                }
                if (!$allowed) {
                    $child->removeAttribute($attr->nodeName);
                }
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener');
            }
            // <span>/<div> sans attribut : simple conteneur, <div> devient paragraphe
            if ($tag === 'span') {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
            } elseif ($tag === 'div') {
                $p = $child->ownerDocument->createElement('p');
                while ($child->firstChild) {
                    $p->appendChild($child->firstChild);
                }
                $node->replaceChild($p, $child);
            }
        }
    }
}
