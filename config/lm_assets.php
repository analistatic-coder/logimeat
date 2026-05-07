<?php
declare(strict_types=1);

/**
 * Rutas a CSS/JS servidos desde el propio servidor (sin CDN) para uso sin internet.
 *
 * Regenerar tailwind-built.css (tras añadir clases Tailwind): ejecutable standalone v3.4.x desde
 * https://github.com/tailwindlabs/tailwindcss/releases — comando con globs recursivos de archivos .php
 * en la raíz del proyecto (ver comentarios en el repo; evitar * y barra juntos en bloques DocBlock).
 */
// Rebuild Tailwind (ejemplo): tailwindcss.exe -i assets/vendor/tailwind-source.css -o assets/vendor/tailwind-built.css --minify --content "**/*.php"

if (!function_exists('lm_app_base_path')) {
    /**
     * Segmento de URL entre el host y los archivos estáticos (sin slashes extremos).
     * Vacío si la app está en la raíz del virtual host.
     * Forzar: constante LM_WEB_BASE o clave web_base en conexion.local.php.
     */
    function lm_app_base_path(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        if (defined('LM_WEB_BASE')) {
            $cached = trim((string) LM_WEB_BASE, '/');

            return $cached;
        }

        $envBase = getenv('LM_WEB_BASE');
        if ($envBase !== false && $envBase !== '') {
            $cached = trim($envBase, '/');

            return $cached;
        }

        $localPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'conexion.local.php';
        if (is_readable($localPath)) {
            $local = require $localPath;
            if (is_array($local) && isset($local['web_base']) && trim((string) $local['web_base']) !== '') {
                $cached = trim((string) $local['web_base'], '/');

                return $cached;
            }
        }

        $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $docReal = ($doc !== '' && is_dir($doc)) ? realpath($doc) : false;
        $appRoot = dirname(__DIR__);
        $appReal = is_dir($appRoot) ? realpath($appRoot) : false;

        if ($docReal !== false && $appReal !== false) {
            $docN = str_replace('\\', '/', $docReal);
            $appN = str_replace('\\', '/', $appReal);
            if (str_starts_with($appN, $docN)) {
                $rel = substr($appN, strlen($docN));
                $rel = trim(str_replace('\\', '/', $rel), '/');
                $cached = $rel;

                return $cached;
            }
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script !== '' && $script[0] !== '/') {
            $script = '/' . $script;
        }
        $dir = dirname($script === '' ? '/' : $script);
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            $cached = '';
        } else {
            $cached = trim($dir, '/');
        }

        return $cached;
    }
}

if (!function_exists('lm_asset_href')) {
    function lm_asset_href(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $base = lm_app_base_path();
        $url = ($base === '') ? '/' . $path : '/' . $base . '/' . $path;

        return preg_replace('#/+#', '/', $url);
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
