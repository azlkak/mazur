<?php
declare(strict_types=1);
// PHP 8.2+, ext-zip, simplexml, pdo_mysql. CLI only; no web trigger.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
const ROOT = __DIR__ . '/..';
function fail(string $message): never { throw new RuntimeException($message); }
function xml(string $bytes): SimpleXMLElement {
    // No DTDs, entities, remote references or UTF-16 bypasses.
    if (str_contains($bytes, "\0") || preg_match('/<!DOCTYPE|<!ENTITY/i', $bytes)) fail('Unsafe XML');
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($bytes, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    if ($doc === false) { libxml_clear_errors(); fail('Invalid XML'); }
    return $doc;
}
function text(SimpleXMLElement $node, string $key): string { return trim((string)$node->$key); }
function event(string $message): void {
    $line = gmdate('c') . ' ' . $message . "\n";
    if (file_put_contents(ROOT . '/logs/import.log', $line, FILE_APPEND | LOCK_EX) === false) fail('Cannot write log');
    echo $line;
}
function recordRun(string $source, string $result, string $error = ''): void {
    if (!in_array($source, ['mls', 'esticrm'], true)) return;
    $folder = ROOT . '/logs';
    if (!is_dir($folder)) return;
    $path = $folder . '/status-' . $source . '.json';
    $previous = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if (!is_array($previous)) $previous = [];
    $now = time();
    $status = [
        'result' => $result,
        'last_run_at' => $now,
        'last_success_at' => (int)($previous['last_success_at'] ?? 0),
        'last_error_at' => (int)($previous['last_error_at'] ?? 0),
        'last_error' => (string)($previous['last_error'] ?? ''),
    ];
    if ($result === 'success') {
        $status['last_success_at'] = $now;
        $status['last_error'] = '';
    } elseif ($result === 'error') {
        $status['last_error_at'] = $now;
        $status['last_error'] = substr($error, 0, 200);
    }
    $temp = @tempnam($folder, '.status-');
    if ($temp === false) return;
    try {
        if (@file_put_contents($temp, json_encode($status, JSON_THROW_ON_ERROR), LOCK_EX) !== false) @rename($temp, $path);
    } catch (Throwable $ignored) {
        // Monitoring must never stop a valid import.
    } finally {
        if (is_file($temp)) @unlink($temp);
    }
}
function reportSkipped(string $source, string $sha, string $file, array $skipped, array $config): void {
    if ($skipped === []) return;
    $report = ['source' => $source, 'batch' => $file, 'skipped_count' => count($skipped), 'skipped' => $skipped];
    $json = json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_put_contents(ROOT . '/logs/skipped-' . $sha . '.json', $json . "\n", LOCK_EX) === false) {
        event('WARNING: cannot save skipped-offer report for ' . $sha);
    }
    event('WARNING: skipped ' . count($skipped) . ' offer records in batch ' . $sha);
    $recipients = $config['notification_emails'] ?? [];
    if (!is_array($recipients)) { event('WARNING: invalid notification email configuration'); return; }
    $sourceLabel = $source === 'esticrm' ? 'EstiCRM' : 'MLS';
    $lines = ["Paczka $sourceLabel: $file", 'Pominięte rekordy: ' . count($skipped), ''];
    foreach (array_slice($skipped, 0, 30) as $issue) {
        $lines[] = 'Oferta ' . ($issue['id'] ?: '(pusty numer)') . ': ' . $issue['reason'];
    }
    if (count($skipped) > 30) $lines[] = 'Pozostałe wpisy są w raporcie na serwerze.';
    foreach ($recipients as $to) {
        if (!is_string($to) || !filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
            event('WARNING: invalid notification email configuration');
            continue;
        }
        $sent = mail($to, 'Mazur Estate - raport importu ' . $sourceLabel, implode("\n", $lines), [
            'From' => 'powiadomienia@mazurestate.pl',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
        if (!$sent) event('WARNING: skipped-offer email could not be sent for ' . $sha);
    }
}
// An older failed batch must not replay deletes or a full snapshot over newer data.
class StaleImportPackage extends RuntimeException {}
function assertPackageOrder(string $path, string $latestCommitted): void {
    if (batchTimestamp($path) < $latestCommitted) throw new StaleImportPackage('Older failed package requires reconciliation after a newer import');
}
function safeImportError(Throwable $e): string {
    if ($e instanceof PDOException) return 'Database failure; SQLSTATE ' . preg_replace('/[^A-Z0-9]/', '', (string)$e->getCode()) . '; driver ' . (int)($e->errorInfo[1] ?? 0);
    if ($e instanceof JsonException) return 'Invalid JSON data in package';
    return $e instanceof RuntimeException ? substr($e->getMessage(), 0, 200) : 'Unexpected importer error';
}
function recoveryLog(string $message): void {
    // A log or mail failure must not stop the remaining package queue.
    try { event($message); } catch (Throwable $ignored) { fwrite(STDERR, $message . "\n"); }
}
/** @return resource|null A busy shared lock is an ordinary skipped run. */
function acquireImportLock(string $path, ?callable $acquire = null) {
    $lock = fopen($path, 'c');
    if ($lock === false) fail('Cannot open import lock');
    $wouldBlock = 0;
    $acquire ??= static function($lock, int &$wouldBlock): bool {
        return flock($lock, LOCK_EX | LOCK_NB, $wouldBlock);
    };
    try { $acquired = $acquire($lock, $wouldBlock); }
    catch (Throwable $error) { fclose($lock); throw $error; }
    if ($acquired) return $lock;
    fclose($lock);
    if ($wouldBlock) return null;
    fail('Cannot acquire import lock');
}
function saveFailureReport(string $path, array $report): void {
    if (!is_dir(dirname($path)) || !is_writable(dirname($path))) fail('Cannot write failure report directory');
    $tmp = tempnam(dirname($path), '.failure-');
    if ($tmp === false) fail('Cannot create failure report');
    try {
        $json = json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json) || !rename($tmp, $path)) fail('Cannot save failure report');
    } finally { if (is_file($tmp)) unlink($tmp); }
}
function reportPackageFailure(string $source, array $report, array $config): void {
    $label = $source === 'esticrm' ? 'EstiCRM' : 'MLS';
    $recovered = $report['result'] === 'recovered';
    $lines = [
        'Źródło: ' . $label,
        'Paczka: ' . $report['file'],
        'Pierwszy błąd: ' . $report['first_error'],
        'Ostatni błąd: ' . $report['last_error'],
        'Liczba prób: ' . $report['attempts'],
        $recovered ? 'Importer ponownie działa poprawnie.' : (($report['file'] === '(uruchomienie importera)')
            ? 'Nie udało się uruchomić importera. Kolejna próba nastąpi zgodnie z harmonogramem.'
            : 'Paczka pozostaje w katalogu errors; pozostałe paczki są przetwarzane.'),
    ];
    if ($report['manual_reconciliation'] ?? false) $lines[] = 'Zaimportowano już nowsze dane. Ta paczka wymaga ręcznego uzgodnienia albo nowego pełnego eksportu.';
    $recipients = $config['notification_emails'] ?? [];
    if (!is_array($recipients) || $recipients === []) fail('No import notification recipients configured');
    foreach ($recipients as $to) {
        if (!is_string($to) || !filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) fail('Invalid notification email configuration');
        $subject = 'MazurEstate - ' . ($recovered ? 'import ponowiony poprawnie - ' : 'błąd importu - ') . $label;
        if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', implode("\n", $lines), [
            'From' => 'powiadomienia@mazurestate.pl', 'Content-Type' => 'text/plain; charset=UTF-8',
        ])) fail('Could not send import failure notification');
    }
}
function flushFailureNotifications(string $logs, string $source, callable $notify, array &$attempted = []): void {
    foreach (glob($logs . '/batch-failure-' . $source . '-*.json') as $path) {
        $report = json_decode((string)file_get_contents($path), true);
        if (!is_array($report) || !isset($report['result'], $report['last_error'])) continue;
        $signature = $report['result'] . ':' . $report['last_error'];
        if (($report['notified'] ?? '') === $signature) continue;
        if (isset($attempted[$path][$signature])) continue;
        $attempted[$path][$signature] = true;
        try {
            $notify($report);
            $report['notified'] = $signature;
            saveFailureReport($path, $report);
        } catch (Throwable $ignored) {
            recoveryLog($source . ': failure notification was not sent; will retry on next run');
        }
    }
}
/** Each package is isolated; one failure can never abort later queue items. */
function processImportQueue(string $feedRoot, array $c, string $source, callable $import, callable $notify): array {
    $logs = $source === 'esticrm' ? dirname($feedRoot) . '/logs' : $feedRoot . '/logs';
    $files = array_merge(glob($feedRoot . '/processing/*.zip'), glob($feedRoot . '/incoming/*.zip'), glob($feedRoot . '/errors/*.zip'));
    $stamp = function(string $path): string {
        try { return batchTimestamp($path); } catch (RuntimeException $ignored) { return ''; }
    };
    usort($files, fn($a, $b) => [$stamp($a), basename($a)] <=> [$stamp($b), basename($b)]);
    $summary = ['imported' => 0, 'failed' => 0, 'last_error' => ''];
    $notificationAttempts = [];
    $limit = max(1, min(3, (int)($c['import_retry_attempts'] ?? 2)));
    foreach ($files as $src) {
        if (!is_link($src) && time() - filemtime($src) < $c['min_age_seconds']) continue;
        $key = !is_link($src) ? hash_file('sha256', $src) : false;
        $key = $key ?: hash('sha256', basename($src));
        $reportPath = $logs . '/batch-failure-' . $source . '-' . $key . '.json';
        $previous = is_file($reportPath) ? json_decode((string)file_get_contents($reportPath), true) : [];
        $report = is_array($previous) ? $previous : [];
        $report += ['source' => $source, 'file' => basename($src), 'attempts' => 0, 'first_error' => '', 'last_error' => '', 'first_error_at' => 0];
        $path = $src;
        $ok = false;
        if (!empty($report['manual_reconciliation'])) {
            $summary['failed']++;
            $summary['last_error'] = $report['last_error'];
            continue;
        }
        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $report['attempts']++;
            try {
                if (is_link($path)) fail('Symlink package rejected');
                batchTimestamp($path);
                $claim = $feedRoot . '/processing/' . basename($path);
                if ($path !== $claim) {
                    if (file_exists($claim) || !rename($path, $claim)) fail('Cannot claim package');
                    $path = $claim;
                }
                $import($path);
                $ok = true;
                $summary['imported']++;
                if ($report['first_error'] !== '') $report['result'] = 'recovered';
                break;
            } catch (Throwable $e) {
                $message = safeImportError($e);
                if ($report['first_error'] === '') {
                    $report['first_error'] = $message;
                    $report['first_error_at'] = time();
                }
                $report['last_error'] = $message;
                $report['last_error_at'] = time();
                $report['result'] = 'failed';
                recoveryLog($source . ': package ' . basename($src) . ' attempt ' . $report['attempts'] . ' failed: ' . $message);
                if ($e instanceof StaleImportPackage) { $report['manual_reconciliation'] = true; break; }
                // Retry right away once; repeated cron runs are the backoff for persistent faults.
            }
        }
        if (!$ok) {
            $summary['failed']++;
            $summary['last_error'] = $report['last_error'];
            $target = $feedRoot . '/errors/' . basename($path);
            if (dirname($path) !== $feedRoot . '/errors' && (is_file($path) || is_link($path))) {
                if (file_exists($target)) $target = $feedRoot . '/errors/' . bin2hex(random_bytes(8)) . '-' . basename($path);
                if (!rename($path, $target)) recoveryLog($source . ': cannot quarantine ' . basename($path));
            }
        }
        if ($report['first_error'] === '') continue;
        // Keep the original failure independently of the latest cron status.
        try { saveFailureReport($reportPath, $report); }
        catch (Throwable $ignored) {
            recoveryLog($source . ': cannot persist failure report');
            // A full disk must not also suppress the only failure alert.
            try { $notify($report); }
            catch (Throwable $ignoredMail) { recoveryLog($source . ': cannot send failure notification'); }
        }
        flushFailureNotifications($logs, $source, $notify, $notificationAttempts);
    }
    // Retry undelivered notifications even if the ZIP was already archived.
    flushFailureNotifications($logs, $source, $notify, $notificationAttempts);
    return $summary;
}

function batchTimestamp(string $path): string {
    // MLS sends YYYYMMDDHHMMSS; older example packages include a separator.
    if (!preg_match('/_([0-9]{8})_?([0-9]{6})\.zip\z/', basename($path), $match)) fail('Unrecognized batch timestamp');
    return $match[1] . $match[2];
}
function isMazowieckie(array $fields): bool {
    $exportProvince = trim((string)($fields['locationExportProvinceName'] ?? ''));
    $province = $exportProvince !== '' ? $exportProvince : trim((string)($fields['locationProvinceName'] ?? ''));
    return strtoupper($province) === 'MAZOWIECKIE';
}
function isSupportedRegion(array $fields, string $source): bool {
    return $source !== 'mls' || isMazowieckie($fields);
}
function inspect(string $path, array $c, string $source): array {
    if (is_link($path) || filesize($path) > $c['max_zip_bytes']) fail('ZIP exceeds limit or is a symlink');
    $z = new ZipArchive();
    if ($z->open($path, ZipArchive::CHECKCONS) !== true) fail('Invalid or incomplete ZIP');
    try {
        if ($z->numFiles > $c['max_entries']) fail('Too many ZIP entries');
        $entries = []; $total = 0; $offerFile = null;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $s = $z->statIndex($i); if ($s === false) fail('Unreadable ZIP entry');
            $n = $s['name'];
            // Flat EstiCRM package only; never extract arbitrary paths.
            if (!preg_match('/\A[A-Za-z0-9_-]+\.(xml|jpg|jpeg|png)\z/i', $n) || isset($entries[$n])) fail('Unsupported or duplicate ZIP filename');
            $opsys = 0; $attr = 0;
            $z->getExternalAttributesIndex($i, $opsys, $attr);
            if ((($attr >> 16) & 0170000) === 0120000) fail('ZIP symlink rejected');
            $total += $s['size'];
            $isXml = strtolower(pathinfo($n, PATHINFO_EXTENSION)) === 'xml';
            if ($s['size'] > ($isXml ? $c['max_xml_bytes'] : $c['max_image_bytes'])) fail('Entry exceeds size limit');
            if ($total > $c['max_expanded_bytes']) fail('Expanded ZIP exceeds limit');
            if ($isXml && $n !== 'definitions.xml') {
                if ($offerFile !== null) fail('Multiple offer XML files');
                $offerFile = $n;
            }
            $entries[$n] = $s;
        }
        if ($offerFile === null || !isset($entries['definitions.xml'])) fail('Missing XML files');
        $read = function(string $n) use ($z, $entries): string {
            $b = $z->getFromName($n);
            if ($b === false || strlen($b) !== $entries[$n]['size']) fail('Unreadable entry');
            if (sprintf('%u', crc32($b)) !== sprintf('%u', $entries[$n]['crc'])) fail('CRC mismatch');
            return $b;
        };
        $defs = xml($read('definitions.xml'));
        if ($defs->getName() !== 'definitions') fail('Invalid definitions root');
        $doc = xml($read($offerFile));
        $exportType = (string)$doc['export'];
        if ($doc->getName() !== 'offers' || !in_array($exportType, ['incremental', 'full'], true)) fail('Unsupported export type');
        $rows = []; $images = []; $rowXmlHashes = []; $skipped = []; $blockedIds = [];
        foreach ($doc->children() as $o) {
            if ($o->getName() !== 'offer') fail('Unknown XML record');
            $id = text($o, 'id');
            if (!preg_match('/\A[0-9]{1,40}\z/', $id)) {
                $skipped[] = ['id' => preg_replace('/[^A-Za-z0-9_-]/', '?', substr($id, 0, 40)), 'reason' => 'invalid_id'];
                continue;
            }
            if (isset($blockedIds[$id])) {
                $skipped[] = ['id' => $id, 'reason' => 'conflicting_duplicate'];
                continue;
            }
            $xmlHash = hash('sha256', $o->asXML());
            if (isset($rowXmlHashes[$id])) {
                if ($rowXmlHashes[$id] !== $xmlHash) {
                    unset($rows[$id]);
                    $blockedIds[$id] = true;
                    $skipped[] = ['id' => $id, 'reason' => 'conflicting_duplicate'];
                } else {
                    $skipped[] = ['id' => $id, 'reason' => 'identical_duplicate'];
                }
                continue;
            }
            $rowXmlHashes[$id] = $xmlHash;
            $action = text($o, 'action');
            if (!in_array($action, ['create', 'update', 'delete'], true)) fail('Unsupported offer action');
            if ($exportType === 'full' && $action === 'delete') fail('Full export may only contain active offers');
            $stamp = text($o, 'exportDate');
            if ($stamp !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $stamp);
                if (!$date || $date->format('Y-m-d H:i:s') !== $stamp) fail('Invalid export timestamp');
            } else {
                $packageDate = DateTimeImmutable::createFromFormat('!YmdHis', batchTimestamp($path));
                if (!$packageDate) fail('Invalid package timestamp');
                $stamp = $packageDate->format('Y-m-d H:i:s');
            }
            $fields = [];
            foreach ($o->children() as $key => $value) {
                if ($key !== 'pictures') {
                    if (isset($fields[$key]) || count($value->children())) fail('Unexpected offer structure');
                    $fields[$key] = (string)$value;
                }
            }
            // MLS remains Mazowieckie-only. EstiCRM has no region filter.
            // Keep excluded IDs in full snapshots, without storing their images.
            if ($action !== 'delete' && !isSupportedRegion($fields, $source)) {
                $rows[$id] = ['action'=>$action, 'stamp'=>$stamp, 'fields'=>$fields, 'gallery'=>null, 'out_of_region'=>true];
                continue;
            }
            $gallery = null;
            if ($action !== 'delete' && isset($o->pictures)) {
                $gallery = [];
                foreach ($o->pictures->picture as $picture) {
                    $name = trim((string)$picture);
                    if (!isset($entries[$name]) || !preg_match('/\.(jpg|jpeg|png)\z/i', $name)) fail('Missing image');
                    if (!isset($images[$name])) {
                        $bytes = $read($name);
                        $info = @getimagesizefromstring($bytes);
                        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) fail('Invalid image');
                        if ($info[0] * $info[1] > 50000000) fail('Image dimensions exceed limit');
                        $images[$name] = hash('sha256', $bytes) . ($info[2] === IMAGETYPE_JPEG ? '.jpg' : '.png');
                    }
                    $gallery[] = $images[$name];
                }
            }
            $rows[$id] = ['action'=>$action, 'stamp'=>$stamp, 'fields'=>$fields, 'gallery'=>$gallery];
        }
        if ($exportType === 'full' && count($rows) < ($c['min_full_offers'] ?? 1)) fail('Full export is unexpectedly empty');
        return [$rows, $images, $total, $exportType, $skipped];
    } finally { $z->close(); }
}

function importPackage(string $path, array $c, string $source, string $feedRoot, string &$latestCommitted): void {
    $db = null;
    $exportType = null;
    try {
        $sha = hash_file('sha256', $path);
        $db = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
        $q = $db->prepare('SELECT sha256 FROM mls_batches WHERE source=? AND sha256=?'); $q->execute([$source,$sha]);
        $skipped = [];
        if (!$q->fetchColumn()) {
            assertPackageOrder($path, $latestCommitted);
            [$rows, $images, $total, $exportType, $skipped] = inspect($path, $c, $source);
            if (disk_free_space(ROOT) < $total + 512 * 1024 ** 2) fail('Insufficient staging space');
            $z = new ZipArchive(); if ($z->open($path) !== true) fail('Cannot reopen ZIP');
            try {
                foreach ($images as $name=>$target) {
                    $dest = ROOT . '/images/' . $target;
                    if (file_exists($dest)) {
                        if (hash_file('sha256', $dest) !== substr($target,0,64)) fail('Stored image corrupted');
                        continue;
                    }
                    $bytes = $z->getFromName($name);
                    if ($bytes === false || hash('sha256',$bytes) !== substr($target,0,64)) fail('Package changed during import');
                    $tmp = tempnam(ROOT . '/images', '.tmp-');
                    if ($tmp === false || file_put_contents($tmp,$bytes) !== strlen($bytes) || !rename($tmp,$dest)) fail('Cannot store image');
                }
            } finally { $z->close(); }
            if (hash_file('sha256', $path) !== $sha) fail('Package changed during import');
            $db = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
            $db->beginTransaction();
            $get = $db->prepare('SELECT source_export_at, images_json FROM mls_offers WHERE source=? AND source_id=? FOR UPDATE');
            $put = $db->prepare('INSERT INTO mls_offers (source,source_id,source_export_at,batch_sha256,publishable,location_city,location_district,fields_json,images_json) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_export_at=VALUES(source_export_at),batch_sha256=VALUES(batch_sha256),publishable=VALUES(publishable),location_city=VALUES(location_city),location_district=VALUES(location_district),fields_json=VALUES(fields_json),images_json=VALUES(images_json)');
            $hideOutsideRegion = $db->prepare('UPDATE mls_offers SET source_export_at=?, batch_sha256=?, publishable=0, fields_json=? WHERE source=? AND source_id=?');
            $delete = $db->prepare('DELETE FROM mls_offers WHERE source=? AND source_id=?');
            if ($exportType === 'full') {
                $db->exec('DROP TEMPORARY TABLE IF EXISTS mls_full_ids');
                $db->exec('CREATE TEMPORARY TABLE mls_full_ids (source_id VARCHAR(40) CHARACTER SET ascii PRIMARY KEY) ENGINE=MEMORY');
                $seen = $db->prepare('INSERT INTO mls_full_ids (source_id) VALUES (?)');
            }
            foreach ($rows as $id=>$r) {
                if ($exportType === 'full') $seen->execute([$id]);
                $get->execute([$source,$id]); $old = $get->fetch(PDO::FETCH_ASSOC);
                if ($r['action'] === 'delete') {
                    if (!$old || $r['stamp'] === '' || $old['source_export_at'] <= $r['stamp']) $delete->execute([$source,$id]);
                    continue;
                }
                if ($old && $old['source_export_at'] > $r['stamp']) continue;
                if (!empty($r['out_of_region'])) {
                    if ($old) $hideOutsideRegion->execute([$r['stamp'],$sha,json_encode($r['fields'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$source,$id]);
                    continue;
                }
                $gallery = $r['gallery'] ?? ($old ? json_decode($old['images_json'],true,512,JSON_THROW_ON_ERROR) : []);
                $f = $r['fields'];
                // Conservative candidate flag. Public API remains disabled separately.
                $visible = ($f['status'] ?? '') === '3' && isSupportedRegion($f, $source) && ($source === 'esticrm' || ($f['offerExport'] ?? '') === '1');
                $city = trim((string)(($f['locationExportCityName'] ?? '') ?: ($f['locationCityName'] ?? '')));
                $district = trim((string)(($f['locationExportPrecinctName'] ?? '') ?: ($f['locationPrecinctName'] ?? '')));
                $put->execute([$source,$id,$r['stamp'],$sha,(int)$visible,$city,$district,json_encode($f,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($gallery,JSON_THROW_ON_ERROR)]);
            }
            if ($exportType === 'full') {
                $remove = $db->prepare('DELETE o FROM mls_offers o LEFT JOIN mls_full_ids f ON f.source_id=o.source_id WHERE o.source=? AND f.source_id IS NULL');
                $remove->execute([$source]);
            }
            // The location API reads these columns, so the same transaction
            // adds, updates and removes suggestions with the offers.
            $locationCheck = $db->prepare("SELECT COUNT(*) AS total, SUM(location_city = '') AS missing_city FROM mls_offers WHERE source=? AND publishable = 1");
            $locationCheck->execute([$source]); $locationCheck = $locationCheck->fetch(PDO::FETCH_ASSOC);
            $q = $db->prepare('INSERT INTO mls_batches (source,sha256,file_name,export_type,offer_count) VALUES (?,?,?,?,?)');
            $q->execute([$source,$sha,basename($path),$exportType,count($rows)]); $db->commit();
            $latestCommitted = max($latestCommitted, batchTimestamp($path));
            recoveryLog($source . ': ' . ((int)$locationCheck['missing_city'] > 0 ? 'WARNING ' : '') . 'Location search checked: ' . (int)$locationCheck['total'] . ' publishable offers, ' . (int)$locationCheck['missing_city'] . ' without city');
        }
        $archivePath = $feedRoot . '/archive/' . $sha . '.zip';
        if (is_file($archivePath)) {
            if (!unlink($path)) fail('Cannot remove duplicate package');
        } elseif (!rename($path, $archivePath)) fail('Cannot archive committed package');
        recoveryLog($source . ': imported batch ' . $sha);
        try { reportSkipped($source, $sha, basename($path), $skipped, $c); }
        catch (Throwable $notificationError) { recoveryLog('WARNING: could not send skipped-offer report for ' . $sha); }
        try {
            $keepDays = max(1, (int)($c['archive_retention_days'] ?? 7));
            foreach (glob($feedRoot . '/archive/*.zip') as $oldArchive) {
                if (filemtime($oldArchive) < time() - $keepDays * 86400) @unlink($oldArchive);
            }
            if (($exportType ?? null) === 'full') {
                $used = [];
                foreach ($db->query('SELECT images_json FROM mls_offers') as $record) {
                    foreach (json_decode($record['images_json'], true, 512, JSON_THROW_ON_ERROR) as $image) $used[$image] = true;
                }
                foreach (glob(ROOT . '/images/*') as $stored) if (is_file($stored) && !isset($used[basename($stored)])) @unlink($stored);
            }
        } catch (Throwable $cleanupError) {
            // The batch is committed and archived; housekeeping cannot undo it.
            recoveryLog($source . ': WARNING archive/image cleanup failed: ' . safeImportError($cleanupError));
        }
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

$source = 'mls';
try {
    $args = array_slice($argv, 1);
    $source = in_array('--source=esticrm', $args, true) ? 'esticrm' : 'mls';
    $dry = in_array('--check', $args, true);
    foreach (['zip', 'SimpleXML', 'pdo_mysql'] as $ext) if (!extension_loaded($ext)) fail('Missing extension: ' . $ext);
    $checkPath = null;
    foreach ($args as $arg) {
        if ($arg === '--check' || $arg === '--source=esticrm') continue;
        if ($dry && $checkPath === null && str_starts_with($arg, '/')) { $checkPath = $arg; continue; }
        fail('Unsupported importer argument');
    }
    $c = require ROOT . (is_file(ROOT . '/config.php') ? '/config.php' : '/config.example.php');
    if ($dry) {
        if ($checkPath === null) fail('Usage: php import.php [--source=esticrm] --check /absolute/package.zip');
        [$rows, $images, $bytes, $exportType, $skipped] = inspect($checkPath, $c, $source);
        $inRegion = count(array_filter($rows, fn($row) => empty($row['out_of_region']) && $row['action'] !== 'delete'));
        echo json_encode(['export'=>$exportType,'offers'=>count($rows),'offers_in_region'=>$inRegion,'images'=>count($images),'expanded_bytes'=>$bytes,'skipped_count'=>count($skipped),'skipped'=>array_slice($skipped,0,30)], JSON_PRETTY_PRINT) . "\n";
        exit;
    }
    if ($source === 'mls' && !$c['enabled']) fail('MLS importer disabled');
    if ($source === 'esticrm' && !($c['esticrm_import_enabled'] ?? false)) fail('EstiCRM importer disabled');
    $feedRoot = $source === 'mls' ? ROOT : ROOT . '/esticrm';
    foreach (['incoming', 'processing', 'archive', 'errors'] as $directory) {
        if (!is_dir($feedRoot . '/' . $directory)) fail('Missing feed directory: ' . $directory);
    }
    // Both feeds share an image store; serialize imports and orphan cleanup.
    $lock = acquireImportLock(ROOT . '/import.lock');
    if ($lock === null) {
        recoveryLog($source . ': another import is running; skipped until the next scheduled run');
        exit(0);
    }
    recordRun($source, 'running');
    $db = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $latestCommitted = '';
    foreach ($db->query('SELECT file_name FROM mls_batches WHERE source=' . $db->quote($source)) as $batch) {
        try { $latestCommitted = max($latestCommitted, batchTimestamp($batch['file_name'])); }
        catch (RuntimeException $ignored) { /* Legacy unrecognised names are not a watermark. */ }
    }
    $db = null;
    $runReportPath = ROOT . '/logs/batch-failure-' . $source . '-run.json';
    if (is_file($runReportPath)) {
        $runReport = json_decode((string)file_get_contents($runReportPath), true);
        if (is_array($runReport) && ($runReport['result'] ?? '') === 'failed') {
            $runReport['result'] = 'recovered';
            try { saveFailureReport($runReportPath, $runReport); } catch (Throwable $ignored) {}
        }
    }
    $summary = processImportQueue($feedRoot, $c, $source,
        function(string $path) use ($c, $source, $feedRoot, &$latestCommitted): void {
            importPackage($path, $c, $source, $feedRoot, $latestCommitted);
        },
        function(array $report) use ($c, $source): void { reportPackageFailure($source, $report, $c); }
    );
    $unresolved = count(glob($feedRoot . '/errors/*.zip'));
    if ($summary['failed'] > 0 || $unresolved > 0) {
        $message = $summary['last_error'] ?: 'Unresolved packages remain; later packages continue to import';
        recordRun($source, 'error', $message);
        exit(1);
    }
    recordRun($source, 'success');
} catch (Throwable $e) {
    // Avoid logging PDO errors containing connection details.
    $message = safeImportError($e);
    if ($dry ?? false) { fwrite(STDERR, $message . "\n"); exit(1); }
    recoveryLog($source . ': importer stopped: ' . $message);
    recordRun($source, 'error', $message);
    if (isset($c) && is_array($c) && is_dir(ROOT . '/logs')) {
        $path = ROOT . '/logs/batch-failure-' . $source . '-run.json';
        $previous = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        $report = is_array($previous) ? $previous : [];
        $report += ['source' => $source, 'file' => '(uruchomienie importera)', 'attempts' => 0, 'first_error' => $message, 'first_error_at' => time()];
        $report['attempts']++;
        $report['result'] = 'failed';
        $report['last_error'] = $message;
        $report['last_error_at'] = time();
        try {
            saveFailureReport($path, $report);
            flushFailureNotifications(ROOT . '/logs', $source, fn($report) => reportPackageFailure($source, $report, $c));
        } catch (Throwable $ignored) { recoveryLog($source . ': cannot report importer failure'); }
    }
    fwrite(STDERR, $message . "\n");
    exit(1);
}
