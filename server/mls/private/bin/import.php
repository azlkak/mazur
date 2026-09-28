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
function inspect(string $path, array $c): array {
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
        $rows = []; $images = [];
        foreach ($doc->children() as $o) {
            if ($o->getName() !== 'offer') fail('Unknown XML record');
            $id = text($o, 'id');
            if (!preg_match('/\A[0-9]{1,40}\z/', $id) || isset($rows[$id])) fail('Invalid or duplicate offer ID');
            $action = text($o, 'action');
            if (!in_array($action, ['update', 'delete'], true)) fail('Unsupported offer action');
            if ($exportType === 'full' && $action !== 'update') fail('Full export may only contain active offers');
            $stamp = text($o, 'exportDate');
            if ($stamp !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $stamp);
                if (!$date || $date->format('Y-m-d H:i:s') !== $stamp) fail('Invalid export timestamp');
            } elseif ($action !== 'delete') fail('Missing export timestamp');
            $fields = [];
            foreach ($o->children() as $key => $value) {
                if ($key !== 'pictures') {
                    if (isset($fields[$key]) || count($value->children())) fail('Unexpected offer structure');
                    $fields[$key] = (string)$value;
                }
            }
            $gallery = null;
            if ($action === 'update' && isset($o->pictures)) {
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
        return [$rows, $images, $total, $exportType];
    } finally { $z->close(); }
}
try {
    foreach (['zip', 'SimpleXML', 'pdo_mysql'] as $ext) if (!extension_loaded($ext)) fail('Missing extension: ' . $ext);
    $dry = ($argv[1] ?? '') === '--check';
    $c = require ROOT . (is_file(ROOT . '/config.php') ? '/config.php' : '/config.example.php');
    if ($dry) {
        if (!isset($argv[2])) fail('Usage: php import.php --check /absolute/package.zip');
        [$rows, $images, $bytes, $exportType] = inspect($argv[2], $c);
        echo json_encode(['export'=>$exportType,'offers'=>count($rows),'images'=>count($images),'expanded_bytes'=>$bytes], JSON_PRETTY_PRINT) . "\n";
        exit;
    }
    if (!$c['enabled']) fail('Importer disabled: configure and validate on staging first');
    $lock = fopen(ROOT . '/import.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) fail('Another import is running');
    if (glob(ROOT . '/errors/*.zip')) fail('Resolve failed packages before continuing');
    $db = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    $files = array_merge(glob(ROOT . '/processing/*.zip'), glob(ROOT . '/incoming/*.zip'));
    // EstiCRM names end with YYYYMMDD_HHMMSS; reject unknown ordering.
    foreach ($files as $f) if (!preg_match('/_\d{8}_\d{6}\.zip\z/', basename($f))) fail('Unrecognized batch timestamp');
    usort($files, fn($a,$b)=>strcmp(substr(basename($a),-19), substr(basename($b),-19)));
    foreach ($files as $src) {
        if (is_link($src)) fail('Symlink package rejected');
        if (time() - filemtime($src) < $c['min_age_seconds']) break;
        $path = ROOT . '/processing/' . basename($src);
        if ($src !== $path && (file_exists($path) || !rename($src, $path))) fail('Cannot claim package');
        try {
            $sha = hash_file('sha256', $path);
            $q = $db->prepare('SELECT sha256 FROM mls_batches WHERE sha256=?'); $q->execute([$sha]);
            if (!$q->fetchColumn()) {
                [$rows, $images, $total, $exportType] = inspect($path, $c);
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
                $db->beginTransaction();
                $get = $db->prepare('SELECT source_export_at, images_json FROM mls_offers WHERE source_id=? FOR UPDATE');
                $put = $db->prepare('INSERT INTO mls_offers (source_id,source_export_at,batch_sha256,publishable,location_city,location_district,fields_json,images_json) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_export_at=VALUES(source_export_at),batch_sha256=VALUES(batch_sha256),publishable=VALUES(publishable),location_city=VALUES(location_city),location_district=VALUES(location_district),fields_json=VALUES(fields_json),images_json=VALUES(images_json)');
                $delete = $db->prepare('DELETE FROM mls_offers WHERE source_id=?');
                if ($exportType === 'full') {
                    $db->exec('CREATE TEMPORARY TABLE mls_full_ids (source_id VARCHAR(40) CHARACTER SET ascii PRIMARY KEY) ENGINE=MEMORY');
                    $seen = $db->prepare('INSERT INTO mls_full_ids (source_id) VALUES (?)');
                }
                foreach ($rows as $id=>$r) {
                    if ($exportType === 'full') $seen->execute([$id]);
                    $get->execute([$id]); $old = $get->fetch(PDO::FETCH_ASSOC);
                    if ($r['action'] === 'delete') {
                        if (!$old || $r['stamp'] === '' || $old['source_export_at'] <= $r['stamp']) $delete->execute([$id]);
                        continue;
                    }
                    if ($old && $old['source_export_at'] > $r['stamp']) continue;
                    $gallery = $r['gallery'] ?? ($old ? json_decode($old['images_json'],true,512,JSON_THROW_ON_ERROR) : []);
                    $f = $r['fields'];
                    // Conservative candidate flag. Public API remains disabled separately.
                    $visible = ($f['offerExport'] ?? '') === '1' && ($f['status'] ?? '') === '3';
                    $city = trim((string)(($f['locationExportCityName'] ?? '') ?: ($f['locationCityName'] ?? '')));
                    $district = trim((string)(($f['locationExportPrecinctName'] ?? '') ?: ($f['locationPrecinctName'] ?? '')));
                    $put->execute([$id,$r['stamp'],$sha,(int)$visible,$city,$district,json_encode($f,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($gallery,JSON_THROW_ON_ERROR)]);
                }
                if ($exportType === 'full') $db->exec('DELETE o FROM mls_offers o LEFT JOIN mls_full_ids f ON f.source_id=o.source_id WHERE f.source_id IS NULL');
                // The location API reads these columns, so the same transaction
                // adds, updates and removes suggestions with the offers.
                $locationCheck = $db->query("SELECT COUNT(*) AS total, SUM(location_city = '') AS missing_city FROM mls_offers WHERE publishable = 1")->fetch(PDO::FETCH_ASSOC);
                $q = $db->prepare('INSERT INTO mls_batches (sha256,file_name,export_type,offer_count) VALUES (?,?,?,?)');
                $q->execute([$sha,basename($path),$exportType,count($rows)]); $db->commit();
                event(((int)$locationCheck['missing_city'] > 0 ? 'WARNING ' : '') . 'Location search checked: ' . (int)$locationCheck['total'] . ' publishable offers, ' . (int)$locationCheck['missing_city'] . ' without city');
            }
            $archivePath = ROOT . '/archive/' . $sha . '.zip';
            if (is_file($archivePath)) {
                if (!unlink($path)) fail('Cannot remove duplicate package');
            } elseif (!rename($path, $archivePath)) fail('Cannot archive committed package');
            event('Imported batch ' . $sha);
            $keepDays = max(1, (int)($c['archive_retention_days'] ?? 7));
            foreach (glob(ROOT . '/archive/*.zip') as $oldArchive) {
                if (filemtime($oldArchive) < time() - $keepDays * 86400) @unlink($oldArchive);
            }
            if (($exportType ?? null) === 'full') {
                $used = [];
                foreach ($db->query('SELECT images_json FROM mls_offers') as $record) {
                    foreach (json_decode($record['images_json'], true, 512, JSON_THROW_ON_ERROR) as $image) $used[$image] = true;
                }
                foreach (glob(ROOT . '/images/*') as $stored) if (is_file($stored) && !isset($used[basename($stored)])) @unlink($stored);
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if (is_file($path)) rename($path,ROOT . '/errors/' . bin2hex(random_bytes(8)) . '-' . basename($path));
            throw $e;
        }
    }
} catch (Throwable $e) {
    // Avoid logging PDO errors containing connection details.
    $message = $e instanceof PDOException ? 'Database failure; inspect configuration privately' : $e->getMessage();
    fwrite(STDERR, $message . "\n");
    exit(1);
}
