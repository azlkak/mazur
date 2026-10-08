<?php
declare(strict_types=1);

// CLI only. Download the feed to a private temporary file before invoking this script.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);

function failVix(string $message): never { throw new RuntimeException($message); }
function vixText(SimpleXMLElement $node, string $name): string { return trim((string)$node->$name); }

function vixStatus(string $result, string $error = ''): void
{
    $folder = __DIR__ . '/../logs';
    if (!is_dir($folder)) return;
    $path = $folder . '/status-vixcrm.json';
    $previous = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if (!is_array($previous)) $previous = [];
    $now = time();
    $data = [
        'result' => $result, 'last_run_at' => $now,
        'last_success_at' => $result === 'success' ? $now : (int)($previous['last_success_at'] ?? 0),
        'last_error_at' => $result === 'error' ? $now : (int)($previous['last_error_at'] ?? 0),
        'last_error' => $result === 'error' ? mb_substr($error, 0, 200) : '',
    ];
    $tmp = @tempnam($folder, '.vix-status-');
    if ($tmp === false) return;
    try {
        if (@file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) !== false) @rename($tmp, $path);
    } catch (Throwable $ignored) {
        // Monitoring must not stop a valid import.
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
}

function fetchVix(string $url, int $maxBytes): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'vixcrm.com'
        || !preg_match('~\A/xml/realting/[A-Za-z0-9]+\.xml\z~', (string)($parts['path'] ?? ''))
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
        || isset($parts['query']) || isset($parts['fragment'])) {
        failVix('Invalid private feed URL');
    }
    $path = tempnam(sys_get_temp_dir(), 'mazur-vix-');
    if ($path === false) failVix('Cannot create temporary feed file');
    $out = fopen($path, 'wb');
    if ($out === false) { unlink($path); failVix('Cannot open temporary feed file'); }
    $handle = curl_init($url);
    if ($handle === false) { fclose($out); unlink($path); failVix('Cannot initialize feed download'); }
    $size = 0;
    curl_setopt_array($handle, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($out, $maxBytes, &$size): int {
            $size += strlen($chunk);
            return $size <= $maxBytes ? fwrite($out, $chunk) : 0;
        },
    ]);
    try {
        $ok = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($ok === false || $status !== 200 || $size === 0) failVix('Feed download failed');
        return $path;
    } finally {
        curl_close($handle);
        fclose($out);
        if (isset($ok) && ($ok === false || ($status ?? 0) !== 200 || $size === 0)) unlink($path);
    }
}

function readVix(string $path, int $maxBytes): array
{
    if (!is_file($path) || is_link($path) || filesize($path) > $maxBytes) failVix('Invalid or oversized XML file');
    $bytes = file_get_contents($path);
    if ($bytes === false || str_contains($bytes, "\0") || preg_match('/<!DOCTYPE|<!ENTITY/i', $bytes)) failVix('Unsafe XML');
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($bytes, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    if ($xml === false || $xml->getName() !== 'objects') failVix('Invalid VixCRM feed');

    $types = [
        '10' => ['Mieszkanie', '1'],
        // The one type-22 record has an apartment-sized area and room count.
        '22' => ['Mieszkanie', '1'],
        '30' => ['Dom', '2'],
        '40' => ['Lokal komercyjny', '4'],
        '50' => ['Działka', '3'],
    ];
    $rows = [];
    foreach ($xml->object as $object) {
        $id = vixText($object, 'external_id');
        if (!preg_match('/\AO-[0-9]{1,36}\z/', $id) || isset($rows[$id])) failVix('Invalid or duplicate offer ID');
        $type = $types[vixText($object, 'type')] ?? null;
        $deal = vixText($object, 'deal_type');
        $price = vixText($object, 'price');
        $area = vixText($object, 'area');
        $description = trim((string)$object->description->pl);
        if ($type === null || !in_array($deal, ['sale', 'long-rent'], true)
            || !is_numeric($price) || (float)$price <= 0 || !is_numeric($area) || (float)$area <= 0
            || $description === '') failVix('Incomplete offer ' . $id);
        if (vixText($object, 'currency') !== 'PLN') failVix('Unsupported currency in ' . $id);

        $address = vixText($object, 'address');
        $city = trim(explode(',', $address, 2)[0]);
        if ($city === '' || mb_strlen($city) > 191) failVix('Invalid city in ' . $id);
        $photos = [];
        foreach ($object->photos->url as $photo) {
            $url = trim((string)$photo);
            if (!preg_match('~\Ahttps://vixcrm\.com/photo/e/[0-9/]+/photo\.png\z~', $url)) failVix('Invalid photo URL in ' . $id);
            $photos[] = $url;
        }
        if ($photos === []) failVix('Missing photos in ' . $id);
        // This feed has no title field; use only facts present in its XML.
        $areaLabel = rtrim(rtrim(number_format((float)$area, 2, '.', ''), '0'), '.');
        $title = $type[0] . ' ' . $areaLabel . ' m² · ' . $city;
        $fields = [
            'companyId' => 'vixcrm', 'number' => $id, 'numberExport' => $id,
            'portalTitle' => $title, 'typeName' => $type[0], 'mainTypeId' => $type[1],
            'transaction' => $deal === 'sale' ? '131' : '132',
            'price' => (float)$price, 'priceCurrency' => '2',
            'areaTotal' => (float)$area, 'areaUsable' => (float)vixText($object, 'area_living'),
            'areaPlot' => $type[1] === '3' ? (float)$area : 0,
            'apartmentRoomNumber' => (int)vixText($object, 'rooms'),
            'apartmentFloor' => vixText($object, 'floor_num'),
            'buildingFloornumber' => vixText($object, 'floors_cnt'),
            'buildingYear' => vixText($object, 'building_year'),
            'locationCityName' => $city, 'descriptionWebsite' => $description,
        ];
        $rows[$id] = [$city, $fields, array_values(array_unique($photos))];
    }
    if (count($rows) < 200) failVix('Feed has fewer than 200 valid offers; refusing a partial snapshot');
    return [$rows, hash('sha256', $bytes)];
}

$downloadedPath = null;
try {
    $sync = $argc === 2 && ($argv[1] ?? '') === '--sync';
    if (!$sync && ($argc !== 3 || !in_array($argv[1], ['--check', '--import'], true))) failVix('Usage: php import-vixcrm.php --check|--import /absolute/feed.xml, or --sync');
    $configPath = __DIR__ . '/../config.php';
    if (($sync || $argv[1] === '--import') && !is_file($configPath)) failVix('Private config.php is missing');
    $config = require (is_file($configPath) ? $configPath : __DIR__ . '/../config.example.php');
    if ($sync && !($config['vixcrm_import_enabled'] ?? false)) failVix('VixCRM importer disabled');
    $path = $sync ? fetchVix((string)($config['vixcrm_feed_url'] ?? ''), (int)($config['max_xml_bytes'] ?? 134217728)) : $argv[2];
    if ($sync) $downloadedPath = $path;
    if (!str_starts_with($path, '/')) failVix('Use an absolute path');
    [$rows, $sha] = readVix($path, (int)($config['max_xml_bytes'] ?? 134217728));
    echo 'VixCRM: ' . count($rows) . ' offers, ' . array_sum(array_map(static fn(array $row): int => count($row[2]), $rows)) . " photos\n";
    if ($argv[1] === '--check') exit(0);
    if (!($config['vixcrm_import_enabled'] ?? false)) failVix('VixCRM importer disabled');
    $lock = fopen(sys_get_temp_dir() . '/mazur-vixcrm-import.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) failVix('VixCRM import already running');

    vixStatus('running');
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec("SET time_zone = '+00:00'");
    $existing = (int)$db->query("SELECT COUNT(*) FROM mls_offers WHERE source='vixcrm'")->fetchColumn();
    if ($existing > 0 && count($rows) < $existing * 0.7) failVix('Snapshot shrank by over 30%; refusing deletion');
    $mode = (string)($config['vixcrm_publish_mode'] ?? 'none');
    if (!in_array($mode, ['none', 'selected', 'all'], true)) failVix('Invalid publication mode');
    $selected = $config['vixcrm_publish_ids'] ?? [];
    if (!is_array($selected)) failVix('Invalid publication IDs');
    foreach ($selected as $id) {
        // A selected offer may legitimately disappear from a later snapshot.
        if (!is_string($id) || !preg_match('/\AO-[0-9]{1,36}\z/', $id)) failVix('Invalid publication ID');
    }
    $selected = array_fill_keys($selected, true);
    $stamp = gmdate('Y-m-d H:i:s');
    $db->beginTransaction();
    try {
        $db->exec('CREATE TEMPORARY TABLE vix_full_ids (source_id VARCHAR(40) CHARACTER SET ascii PRIMARY KEY) ENGINE=MEMORY');
        $seen = $db->prepare('INSERT INTO vix_full_ids (source_id) VALUES (?)');
        $put = $db->prepare('INSERT INTO mls_offers (source,source_id,source_export_at,batch_sha256,publishable,location_city,location_district,fields_json,images_json) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_export_at=VALUES(source_export_at),batch_sha256=VALUES(batch_sha256),publishable=VALUES(publishable),location_city=VALUES(location_city),location_district=VALUES(location_district),fields_json=VALUES(fields_json),images_json=VALUES(images_json)');
        foreach ($rows as $id => [$city, $fields, $photos]) {
            $seen->execute([$id]);
            $publish = (int)($mode === 'all' || ($mode === 'selected' && isset($selected[$id])));
            $put->execute(['vixcrm', $id, $stamp, $sha, $publish, $city, '',
                json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                json_encode($photos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]);
        }
        $db->exec("DELETE o FROM mls_offers o LEFT JOIN vix_full_ids f ON f.source_id=o.source_id WHERE o.source='vixcrm' AND f.source_id IS NULL");
        $batch = $db->prepare("INSERT INTO mls_batches (source,sha256,file_name,export_type,offer_count) VALUES ('vixcrm', ?, ?, 'full', ?) ON DUPLICATE KEY UPDATE imported_at=CURRENT_TIMESTAMP, offer_count=VALUES(offer_count)");
        $batch->execute([$sha, basename($path), count($rows)]);
        $db->commit();
        vixStatus('success');
        echo 'Imported VixCRM snapshot; publication mode=' . $mode . "\n";
        if ($downloadedPath !== null) unlink($downloadedPath);
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
} catch (Throwable $error) {
    if ($downloadedPath !== null && is_file($downloadedPath)) unlink($downloadedPath);
    if (($argv[1] ?? '') !== '--check') vixStatus('error', $error->getMessage());
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
