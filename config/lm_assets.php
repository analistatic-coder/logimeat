<?php
declare(strict_types=1);

/**
 * Rutas a CSS/JS servidos desde el propio servidor (sin CDN) para uso sin internet.
 *
 * Regenerar tailwind-built.css (tras añadir clases Tailwind nuevas): ejecutable standalone v3.4.x
 * desde https://github.com/tailwindlabs/tailwindcss/releases — por ejemplo:
 * tailwindcss.exe -i assets/vendor/tailwind-source.css -o assets/vendor/tailwind-built.css --minify --content "**/*.php"
 */
if (!function_exists('lm_asset_web_prefix')) {
    function lm_asset_web_prefix(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $script = str_replace('\\', '/', (string) $script);
        $dir = dirname($script);
        if ($dir === '/' || $dir === '.' || $dir === '') {
            return '';
        }

        return rtrim($dir, '/');
    }
}

if (!function_exists('lm_asset_href')) {
    function lm_asset_href(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $prefix = lm_asset_web_prefix();

        return ($prefix === '' ? '' : $prefix . '/') . $path;
    }
}

if (!function_exists('lm_head_local_assets')) {
    /**
     * @param array{chart?: bool, fullcalendar?: bool} $extra
     */
    function lm_head_local_assets(array $extra = []): void
    {
        $chart = !empty($extra['chart']);
        $fullcalendar = !empty($extra['fullcalendar']);
        $e = ENT_QUOTES | ENT_SUBSTITUTE;
        ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(lm_asset_href('assets/vendor/tailwind-built.css'), $e, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(lm_asset_href('assets/vendor/plus-jakarta-local.css'), $e, 'UTF-8') ?>">
<?php if ($chart) { ?>
    <script src="<?= htmlspecialchars(lm_asset_href('assets/vendor/chart.umd.min.js'), $e, 'UTF-8') ?>"></script>
<?php } ?>
<?php if ($fullcalendar) { ?>
    <script src="<?= htmlspecialchars(lm_asset_href('assets/vendor/fullcalendar.index.global.min.js'), $e, 'UTF-8') ?>"></script>
<?php } ?>
<?php
    }
}
