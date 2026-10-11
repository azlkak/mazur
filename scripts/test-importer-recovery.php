<?php
declare(strict_types=1);
// Exercise the real queue with disposable files and injected import/mail boundaries.
$folder = sys_get_temp_dir() . '/mazur-recovery-' . bin2hex(random_bytes(6));
mkdir($folder, 0700);
$code = file_get_contents(__DIR__ . '/../server/mls/private/bin/import.php');
$main = strpos($code, "\ntry {\n");
if ($main === false) throw new RuntimeException('Importer entry point missing');
$prefix = substr($code, 5, $main - 5);
$prefix = str_replace("const ROOT = __DIR__ . '/..';", 'const ROOT = ' . var_export($folder, true) . ';', $prefix);
eval($prefix);
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function fixtureFeed(string $name): string {
    $root = ROOT . '/' . $name;
    mkdir($root);
    foreach (['incoming', 'processing', 'errors', 'archive', 'logs'] as $dir) mkdir($root . '/' . $dir);
    return $root;
}
function fixturePackage(string $root, string $date, string $content, string $dir = 'incoming'): string {
    $path = $root . '/' . $dir . '/EstiCRM_42_' . $date . '.zip';
    file_put_contents($path, $content); touch($path, time() - 3600); return $path;
}
function archiveFixture(string $path): void { rename($path, dirname(dirname($path)) . '/archive/' . basename($path)); }
function removeFixtures(string $path): void {
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') continue;
        $child = $path . '/' . $name;
        if (is_dir($child)) removeFixtures($child); else unlink($child);
    }
    rmdir($path);
}
mkdir(ROOT . '/logs');
$config = ['min_age_seconds' => 600, 'import_retry_attempts' => 2];
try {
    // Exercise the OS lock boundary without depending on WASM flock support.
    $lockHandle = null;
    $lock = acquireImportLock(ROOT . '/import.lock', function($handle, int &$wouldBlock) use (&$lockHandle): bool {
        $lockHandle = $handle; $wouldBlock = 1; return false;
    });
    check($lock === null && !is_resource($lockHandle), 'Busy shared lock must skip cleanly and close its handle');
    $lockFailure = '';
    try { acquireImportLock(ROOT . '/import.lock', static function($handle, int &$wouldBlock): bool { return false; }); }
    catch (RuntimeException $error) { $lockFailure = $error->getMessage(); }
    check($lockFailure === 'Cannot acquire import lock', 'Actual lock errors must still be reported instead of silently skipped');
    $lock = acquireImportLock(ROOT . '/import.lock', static function($handle, int &$wouldBlock): bool { return true; });
    check(is_resource($lock), 'Acquired lock handle must stay open for the entire import');
    fclose($lock);

    $root = fixtureFeed('recovered');
    fixturePackage($root, '20261010150000', 'temporary');
    fixturePackage($root, '20261010160000', 'good');
    $calls = []; $notices = [];
    $summary = processImportQueue($root, $config, 'mls', function($path) use (&$calls): void {
        $calls[] = basename($path);
        if (count($calls) === 1) throw new RuntimeException('Temporary failure');
        archiveFixture($path);
    }, function($report) use (&$notices): void { $notices[] = $report; });
    check($summary['imported'] === 2 && $summary['failed'] === 0, 'Recovered batch and next batch must import');
    check(count($calls) === 3 && $calls[0] === $calls[1], 'Retry must precede next batch');
    check(count($notices) === 1 && $notices[0]['result'] === 'recovered' && $notices[0]['attempts'] === 2, 'Recovered failure must be reported');
    $saved = json_decode(file_get_contents(glob($root . '/logs/batch-failure-*.json')[0]), true);
    check($saved['first_error'] === 'Temporary failure' && isset($saved['notified']), 'Original error must survive recovery');

    $root = fixtureFeed('persistent');
    fixturePackage($root, '20261010150000', 'bad');
    fixturePackage($root, '20261010160000', 'good');
    $calls = []; $notices = [];
    $import = function($path) use (&$calls): void {
        $calls[] = basename($path);
        if (file_get_contents($path) === 'bad') throw new RuntimeException('Persistent failure');
        archiveFixture($path);
    };
    $notify = function($report) use (&$notices): void { $notices[] = $report; };
    $summary = processImportQueue($root, $config, 'mls', $import, $notify);
    check($summary['imported'] === 1 && $summary['failed'] === 1 && count($calls) === 3, 'Permanent failure must not block later batch');
    check(count(glob($root . '/errors/*.zip')) === 1, 'Failed package must be quarantined');
    processImportQueue($root, $config, 'mls', $import, $notify);
    check(count($calls) === 5 && count($notices) === 1, 'Next run retries without repeating identical alert');
    $summary = processImportQueue($root, $config, 'mls', function($path): void { assertPackageOrder($path, '20261010160000'); }, $notify);
    check($summary['failed'] === 1 && count($notices) === 2 && $notices[1]['manual_reconciliation'], 'Older failed batch must not replay over newer data');
    $called = false;
    processImportQueue($root, $config, 'mls', function() use (&$called): void { $called = true; }, $notify);
    check(!$called && count($notices) === 2, 'Reconciliation package must stay isolated without repeat alerts');
    assertPackageOrder('/tmp/EstiCRM_42_20261010160000.zip', '20261010160000');

    $root = fixtureFeed('young-and-invalid');
    $young = fixturePackage($root, '20261010140000', 'young'); touch($young);
    $invalid = $root . '/incoming/invalid.zip'; file_put_contents($invalid, 'invalid'); touch($invalid, time() - 3600);
    fixturePackage($root, '20261010160000', 'good');
    $summary = processImportQueue($root, $config, 'mls', 'archiveFixture', static function($report): void {});
    check($summary['imported'] === 1 && $summary['failed'] === 1 && is_file($young), 'Young or malformed package must not stop mature valid packages');

    $root = fixtureFeed('mail-retry');
    fixturePackage($root, '20261010150000', 'mail');
    $attempts = 0;
    processImportQueue($root, $config, 'mls', function($path) use (&$attempts): void {
        if (++$attempts === 1) throw new RuntimeException('Transient failure');
        archiveFixture($path);
    }, static function(): void { throw new RuntimeException('Mail unavailable'); });
    $notices = [];
    processImportQueue($root, $config, 'mls', 'archiveFixture', function($report) use (&$notices): void { $notices[] = $report; });
    check(count($notices) === 1 && $notices[0]['result'] === 'recovered', 'Undelivered alert must retry even after ZIP has been archived');

    $root = fixtureFeed('report-unwritable');
    fixturePackage($root, '20261010150000', 'no-report');
    rmdir($root . '/logs'); file_put_contents($root . '/logs', 'not a directory');
    $notices = [];
    $summary = processImportQueue($root, $config, 'mls', static function(): void { throw new RuntimeException('Disk/report unavailable'); }, function($report) use (&$notices): void { $notices[] = $report; });
    check($summary['failed'] === 1 && count($notices) === 1, 'Report persistence failure must not suppress email');

    $root = fixtureFeed('esti'); mkdir($root . '/esticrm');
    foreach (['incoming', 'processing', 'archive', 'errors'] as $dir) mkdir($root . '/esticrm/' . $dir);
    fixturePackage($root . '/esticrm', '20261010150000', 'esti');
    processImportQueue($root . '/esticrm', $config, 'esticrm', static function(): void { throw new RuntimeException('Esti failure'); }, static function(): void {});
    check(count(glob($root . '/logs/batch-failure-esticrm-*.json')) === 1 && glob($root . '/logs/batch-failure-mls-*.json') === [], 'Feed reports must be isolated');

    $error = new PDOException('Password=SECRET');
    check(!str_contains(safeImportError($error), 'SECRET'), 'Database errors must not leak credentials');
    $path = ROOT . '/EstiCRM_42_20261010152903.zip';
    $zip = new ZipArchive(); $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('definitions.xml', '<definitions/>');
    $zip->addFromString('offers.xml', '<offers export="incremental"><offer><id>381044</id><action>delete</action></offer></offers>'); $zip->close();
    $limits = require __DIR__ . '/../server/mls/private/config.example.php';
    [$rows] = inspect($path, $limits, 'mls');
    check($rows[381044]['stamp'] === '2026-10-10 15:29:03', 'Delete without exportDate must use package timestamp');
    echo "Importer recovery, queue isolation, alert deduplication, shared-lock handling and stale-package protection: passed\n";
} finally { removeFixtures(ROOT); }
