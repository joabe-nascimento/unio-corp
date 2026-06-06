<?php

namespace App\Rh;

final class RhSlugger
{
    public static function slugify(string $text, string $fallback = 'item'): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $base = $ascii !== false ? $ascii : $text;
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $base), '-'));

        return $slug !== '' ? $slug : $fallback;
    }

    public static function unique(string $base, callable $exists): string
    {
        $slug = self::slugify($base);
        if (!$exists($slug)) {
            return $slug;
        }
        for ($i = 2; $i <= 99; ++$i) {
            $candidate = $slug . '-' . $i;
            if (!$exists($candidate)) {
                return $candidate;
            }
        }

        return $slug . '-' . bin2hex(random_bytes(3));
    }
}
