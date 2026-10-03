<?php
declare(strict_types=1);
// Public liveness only: no IDs, credentials, paths, counts or error details.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$ok = false;
try {
    require_once __DIR__ . '/api/offer-seo-lib.php';
    $root = dirname(__DIR__, 2) . '/mls';
    if (!is_file($root . '/config.php')) $root = dirname(__DIR__) . '/mls';
    $config = require $root . '/config.php';
    $db = mazurSeoDb();
    $query = $db->query('SELECT 1');
    $ok = (int)$query->fetchColumn() === 1;
    $query->closeCursor();
    foreach (['mls', 'esticrm'] as $source) {
        $enabled = (bool)($config[$source === 'mls' ? 'enabled' : 'esticrm_import_enabled'] ?? false);
        if (!$enabled) { $ok = false; continue; }
        $file = $root . '/logs/status-' . $source . '.json';
        if (!is_file($file) || filesize($file) > 4096) { $ok = false; continue; }
        $status = json_decode((string)file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        $run = (int)($status['last_run_at'] ?? 0);
        if ($run < time() - 8100 || !in_array($status['result'] ?? '', ['success', 'running'], true)) $ok = false;
        if (($status['result'] ?? '') === 'running' && $run < time() - 5400) $ok = false;
        $feedRoot = $source === 'mls' ? $root : $root . '/esticrm';
        if (count(glob($feedRoot . '/errors/*.zip') ?: []) > 0) $ok = false;
        foreach (['incoming' => 8100, 'processing' => 5400] as $folder => $limit) {
            foreach (glob($feedRoot . '/' . $folder . '/*.zip') ?: [] as $package) {
                if (filemtime($package) < time() - $limit) $ok = false;
            }
        }
    }
} catch (Throwable $error) {
    $ok = false;
}
http_response_code($ok ? 200 : 503);
echo json_encode(['ok' => $ok], JSON_THROW_ON_ERROR), "\n";
