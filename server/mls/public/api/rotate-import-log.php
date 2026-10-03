<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
$folder = dirname(__DIR__, 2) . '/mls/logs';
$file = $folder . '/import.log';
if (!is_file($file)) { echo "No importer log to rotate.\n"; exit; }
$handle = fopen($file, 'c+');
if ($handle === false || !flock($handle, LOCK_EX)) {
    fwrite(STDERR, "Cannot lock importer log.\n"); exit(1);
}
try {
    $contents = stream_get_contents($handle);
    if ($contents === false) throw new RuntimeException('Cannot read importer log');
    if ($contents !== '') {
        $archive = $folder . '/rotation-import-' . gmdate('Ymd-His') . '.log';
        $target = fopen($archive, 'x');
        if ($target === false) throw new RuntimeException('Cannot create rotated log');
        $written = fwrite($target, $contents);
        fflush($target); fclose($target);
        if ($written !== strlen($contents)) throw new RuntimeException('Incomplete rotated log; original retained');
        if (!ftruncate($handle, 0)) throw new RuntimeException('Cannot truncate copied log');
        fflush($handle);
        echo 'Rotated importer log: ' . strlen($contents) . " bytes.\n";
    }
} finally {
    flock($handle, LOCK_UN); fclose($handle);
}
// Prune only files created by this script; never packages or diagnostic reports.
$removed = 0;
foreach (glob($folder . '/rotation-import-*.log') ?: [] as $archive) {
    if (!preg_match('/\Arotation-import-\d{8}-\d{6}\.log\z/', basename($archive))) continue;
    if (is_link($archive) || !is_file($archive)) continue;
    if (filemtime($archive) < time() - 30 * 86400) {
        if (!unlink($archive)) throw new RuntimeException('Cannot remove expired rotated log');
        $removed++;
    }
}
echo 'Retention: 30 days; removed ' . $removed . " expired rotated logs.\n";
