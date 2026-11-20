<?php
declare(strict_types=1);

/**
 * Tiny, safe article markup (no HTML allowed). Everything is escaped first.
 *   ## Heading        ### Subheading
 *   - item            1. item
 *   > Callout title   (following "> " lines are the callout body)
 *   **bold**   [text](https://… or /path)
 * Blank lines separate blocks.
 */
final class Markup
{
    public static function render(string $src): string
    {
        $src = str_replace(["\r\n", "\r"], "\n", trim($src));
        if ($src === '') {
            return '';
        }
        $out = [];
        foreach (preg_split('/\n{2,}/', $src) as $block) {
            $lines = array_values(array_filter(array_map('rtrim', explode("\n", $block)), static fn ($l) => $l !== ''));
            if (!$lines) {
                continue;
            }
            $first = $lines[0];
            if (str_starts_with($first, '### ')) {
                $out[] = '<h3>' . self::inline(substr($first, 4)) . '</h3>' . (count($lines) > 1 ? self::paragraph(array_slice($lines, 1)) : '');
            } elseif (str_starts_with($first, '## ')) {
                $out[] = '<h2>' . self::inline(substr($first, 3)) . '</h2>' . (count($lines) > 1 ? self::paragraph(array_slice($lines, 1)) : '');
            } elseif (self::all($lines, '/^- /')) {
                $out[] = '<ul>' . implode('', array_map(static fn ($l) => '<li>' . self::inline(substr($l, 2)) . '</li>', $lines)) . '</ul>';
            } elseif (self::all($lines, '/^\d+\. /')) {
                $out[] = '<ol>' . implode('', array_map(static fn ($l) => '<li>' . self::inline(preg_replace('/^\d+\. /', '', $l)) . '</li>', $lines)) . '</ol>';
            } elseif (self::all($lines, '/^> ?/')) {
                $body = array_map(static fn ($l) => preg_replace('/^> ?/', '', $l), $lines);
                $title = array_shift($body);
                $out[] = '<div class="callout"><strong>' . self::inline($title) . '</strong>' . self::inline(implode(' ', $body)) . '</div>';
            } else {
                $out[] = self::paragraph($lines);
            }
        }
        return implode("\n", $out);
    }

    /** Plain text for search snippets and reading time. */
    public static function plain(string $src): string
    {
        $t = preg_replace(['/^#{2,3} /m', '/^[->] ?/m', '/^\d+\. /m', '/\*\*(.+?)\*\*/', '/\[([^\]]+)\]\([^)]+\)/'], ['', '', '', '$1', '$1'], $src);
        return trim(preg_replace('/\s+/', ' ', (string) $t));
    }

    public static function readingMinutes(string $src): int
    {
        return max(1, (int) round(str_word_count(self::plain($src), 0, 'ÀÁÂÃÄÅàáâãäåÈÉÊËèéêëÌÍÎÏìíîïÒÓÔÕÖòóôõöÙÚÛÜùúûü-') / 200));
    }

    /** "Title | URL" per line → [['title' => …, 'url' => …|null], …] */
    public static function sources(string $src): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim($src)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$title, $u] = array_map('trim', array_pad(explode('|', $line, 2), 2, ''));
            $out[] = ['title' => $title, 'url' => preg_match('#^https?://#i', $u) ? $u : null];
        }
        return $out;
    }

    private static function all(array $lines, string $re): bool
    {
        foreach ($lines as $l) {
            if (!preg_match($re, $l)) {
                return false;
            }
        }
        return true;
    }

    private static function paragraph(array $lines): string
    {
        return '<p>' . self::inline(implode(' ', $lines)) . '</p>';
    }

    private static function inline(string $text): string
    {
        $t = e($text);
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
        return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function (array $m) {
            $href = html_entity_decode($m[2], ENT_QUOTES);
            if (preg_match('#^https?://#i', $href)) {
                return '<a href="' . e($href) . '" rel="noopener noreferrer" target="_blank">' . $m[1] . '</a>';
            }
            if (preg_match('#^/[a-z0-9/_\-\#?=&]*$#i', $href)) {
                return '<a href="' . e(url($href)) . '">' . $m[1] . '</a>';
            }
            return $m[1]; // javascript:, data:, etc. are dropped
        }, (string) $t);
    }
}
