<?php

namespace App\Service;

/**
 * Copia dependências externas de SVG (PNG, etc.) para a pasta do upload
 * e reescreve hrefs para caminhos relativos — necessário para preview e <object>.
 */
final class SvgAssetBundlingService
{
    public function bundleExternalAssets(string $svgPath, string $publicDir): bool
    {
        if (!is_file($svgPath)) {
            return false;
        }

        $content = file_get_contents($svgPath);
        if ($content === false || stripos($content, '<svg') === false) {
            return false;
        }

        $uploadDir = dirname($svgPath);
        $baseName  = pathinfo($svgPath, PATHINFO_FILENAME);
        $changed   = false;

        $content = preg_replace_callback(
            '/\b((?:xlink:)?href)=(["\'])([^"\']+)\2/i',
            function (array $m) use ($publicDir, $uploadDir, $baseName, &$changed) {
                $attr = $m[1];
                $quote = $m[2];
                $href  = trim($m[3]);

                if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'data:')) {
                    return $m[0];
                }

                $source = $this->resolveSourcePath($href, $publicDir, $uploadDir);
                if ($source === null) {
                    return $m[0];
                }

                $ext      = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'bin');
                $destName = $baseName . '-dep-' . substr(md5($href), 0, 8) . '.' . $ext;
                $destPath = $uploadDir . DIRECTORY_SEPARATOR . $destName;

                if (!is_file($destPath)) {
                    copy($source, $destPath);
                }

                $changed = true;

                return $attr . '=' . $quote . $destName . $quote;
            },
            $content
        ) ?? $content;

        if ($changed) {
            file_put_contents($svgPath, $content);
        }

        return $changed;
    }

    private function resolveSourcePath(string $href, string $publicDir, string $uploadDir): ?string
    {
        if (preg_match('#^https?://[^/]+(/.*)$#i', $href, $m)) {
            $href = $m[1];
        }

        if (str_starts_with($href, '/')) {
            $candidate = $publicDir . $href;

            return is_file($candidate) ? $candidate : null;
        }

        $candidate = $uploadDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $href);
        if (is_file($candidate)) {
            return $candidate;
        }

        $fromPublic = $publicDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $href), DIRECTORY_SEPARATOR);

        return is_file($fromPublic) ? $fromPublic : null;
    }
}
