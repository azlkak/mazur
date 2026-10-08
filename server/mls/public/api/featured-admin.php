<?php
declare(strict_types=1);
require_once __DIR__ . '/portal-visibility.php';
require_once __DIR__ . '/offer-source.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'none\'; script-src \'self\'; style-src \'self\'; img-src https://api.mazurestate.pl data:; connect-src \'self\'; base-uri \'none\'; form-action \'none\'; frame-ancestors \'none\'');

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (!$secure) {
    http_response_code(403);
    exit;
}
session_name('mazur_featured_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/api/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.use_strict_mode', '1');
session_start();

function respond(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function db(array $config): PDO
{
    return new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function throttle(string $key, int $limit, int $windowSeconds): int
{
    $folder = dirname(__DIR__, 2) . '/mls/runtime/featured-rate';
    if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) throw new RuntimeException('Rate storage unavailable');
    $handle = fopen($folder . '/' . hash('sha256', $key), 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) throw new RuntimeException('Rate lock unavailable');
    $raw = stream_get_contents($handle);
    $events = json_decode(is_string($raw) ? $raw : '', true);
    if (!is_array($events)) $events = [];
    $now = time();
    $events = array_values(array_filter($events, static fn($at): bool => is_int($at) && $at > $now - $windowSeconds));
    $retryAfter = count($events) >= $limit ? max(1, $events[0] + $windowSeconds - $now) : 0;
    if ($retryAfter === 0) $events[] = $now;
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($events, JSON_THROW_ON_ERROR));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $retryAfter;
}

function integrationStatus(PDO $db, array $config): array
{
    $root = dirname(__DIR__, 2) . '/mls';
    $sourceColumn = $db->query("SHOW COLUMNS FROM mls_batches LIKE 'source'")->fetch() !== false;
    $offerSourceColumn = portalOffersHaveSource($db);
    $feeds = [];
    foreach (['mls', 'esticrm'] as $source) {
        $feedRoot = $source === 'mls' ? $root : $root . '/esticrm';
        $enabled = $source === 'mls' ? (bool)($config['enabled'] ?? false) : (bool)($config['esticrm_import_enabled'] ?? false);
        $countSql = 'SELECT COUNT(*) AS total, COALESCE(SUM(publishable = 1), 0) AS publishable FROM mls_offers';
        if ($offerSourceColumn) $countSql .= ' WHERE source = ?';
        $count = $db->prepare($countSql);
        $count->execute($offerSourceColumn ? [$source] : []);
        $offers = $source === 'esticrm' && !$offerSourceColumn
            ? ['total' => 0, 'publishable' => 0]
            : $count->fetch();

        $batchSql = 'SELECT file_name, export_type, offer_count, UNIX_TIMESTAMP(imported_at) AS imported_at FROM mls_batches';
        if ($sourceColumn) $batchSql .= ' WHERE source = ?';
        $batchSql .= ' ORDER BY imported_at DESC LIMIT 6';
        $query = $db->prepare($batchSql);
        $query->execute($sourceColumn ? [$source] : []);
        $batches = $source === 'esticrm' && !$sourceColumn ? [] : $query->fetchAll();
        foreach ($batches as &$batch) {
            $batch['offer_count'] = (int)$batch['offer_count'];
            $batch['imported_at'] = (int)$batch['imported_at'];
        }
        unset($batch);

        $health = null;
        $healthPath = $root . '/logs/status-' . $source . '.json';
        if (is_file($healthPath) && filesize($healthPath) <= 2048) {
            $decoded = json_decode((string)file_get_contents($healthPath), true);
            if (is_array($decoded)) $health = $decoded;
        }
        $counts = [];
        $oldest = [];
        foreach (['incoming', 'processing', 'errors'] as $folder) {
            $files = glob($feedRoot . '/' . $folder . '/*.zip') ?: [];
            $counts[$folder] = count($files);
            $oldest[$folder] = $files ? min(array_map('filemtime', $files)) : null;
        }
        $lastRun = (int)($health['last_run_at'] ?? 0);
        $state = 'ok';
        $reason = '';
        if (!$enabled) $state = 'disabled';
        elseif ($counts['errors'] > 0 || ($health['result'] ?? '') === 'error') $state = 'error';
        elseif ($lastRun === 0) $state = 'unknown';
        elseif (time() - $lastRun > 8100 || (($health['result'] ?? '') === 'running' && time() - $lastRun > 5400)) { $state = 'stale'; $reason = 'run'; }
        elseif (($oldest['incoming'] ?? 0) && time() - $oldest['incoming'] > 8100) { $state = 'stale'; $reason = 'incoming'; }
        elseif (($oldest['processing'] ?? 0) && time() - $oldest['processing'] > 5400) { $state = 'stale'; $reason = 'processing'; }
        elseif (($health['result'] ?? '') === 'running') $state = 'running';
        elseif ($source === 'mls' && (!$batches || time() - $batches[0]['imported_at'] > 10800)) $state = 'quiet';
        $feeds[$source] = [
            'state' => $state,
            'state_reason' => $reason,
            'enabled' => $enabled,
            'last_run_at' => $lastRun ?: null,
            'last_success_at' => (int)($health['last_success_at'] ?? 0) ?: null,
            'last_error_at' => (int)($health['last_error_at'] ?? 0) ?: null,
            'last_error' => in_array(($health['result'] ?? ''), ['error'], true) ? (string)($health['last_error'] ?? '') : '',
            'offers_total' => (int)$offers['total'],
            'offers_publishable' => (int)$offers['publishable'],
            'incoming' => $counts['incoming'],
            'processing' => $counts['processing'],
            'errors' => $counts['errors'],
            'batches' => $batches,
        ];
    }
    // VixCRM is fetched as a full XML snapshot, without FTP ZIP folders.
    if ($offerSourceColumn && $sourceColumn) {
        $count = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(publishable = 1), 0) AS publishable FROM mls_offers WHERE source='vixcrm'")->fetch();
        $batches = $db->query("SELECT file_name, export_type, offer_count, UNIX_TIMESTAMP(imported_at) AS imported_at FROM mls_batches WHERE source='vixcrm' ORDER BY imported_at DESC LIMIT 6")->fetchAll();
        foreach ($batches as &$batch) {
            $batch['offer_count'] = (int)$batch['offer_count'];
            $batch['imported_at'] = (int)$batch['imported_at'];
        }
        unset($batch);
        $statusPath = $root . '/logs/status-vixcrm.json';
        $health = is_file($statusPath) && filesize($statusPath) <= 2048 ? json_decode((string)file_get_contents($statusPath), true) : null;
        if (!is_array($health)) $health = [];
        $enabled = (bool)($config['vixcrm_import_enabled'] ?? false);
        $state = !$enabled ? 'disabled' : (($health['result'] ?? '') === 'error' ? 'error' : (($health['result'] ?? '') === 'running' ? 'running' : ($batches ? 'ok' : 'unknown')));
        $feeds['vixcrm'] = [
            'state' => $state, 'state_reason' => '', 'enabled' => $enabled,
            'last_run_at' => (int)($health['last_run_at'] ?? 0) ?: null,
            'last_success_at' => (int)($health['last_success_at'] ?? 0) ?: null,
            'last_error_at' => (int)($health['last_error_at'] ?? 0) ?: null,
            'last_error' => $state === 'error' ? (string)($health['last_error'] ?? '') : '',
            'offers_total' => (int)$count['total'],
            'offers_publishable' => (int)$count['publishable'],
            'incoming' => 0, 'processing' => 0, 'errors' => $state === 'error' ? 1 : 0,
            'batches' => $batches,
        ];
    }
    return ['checked_at' => time(), 'retention_days' => max(1, (int)($config['archive_retention_days'] ?? 7)), 'feeds' => $feeds];
}

try {
    $config = require dirname(__DIR__, 2) . '/mls/config.php';
    $allowed = array_map('strtolower', $config['featured_admin_emails'] ?? []);
    $authorized = isset($_SESSION['admin_email']) && in_array($_SESSION['admin_email'], $allowed, true);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && !isset($_GET['action'])) {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/featured-admin.html');
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'status') {
        if (!$authorized) respond(200, ['authorized' => false]);
        $db = db($config);
        [$hiddenIds, $hiddenProperties] = portalHiddenSets($db);
        $sourceCondition = portalOffersHaveSource($db) ? "o.source = 'mls' AND " : '';
        $rows = $db->query("SELECT f.source_id, o.publishable, o.fields_json FROM featured_offers f LEFT JOIN mls_offers o ON $sourceCondition o.source_id = f.source_id ORDER BY f.display_order")->fetchAll();
        foreach ($rows as &$row) {
            if ($row['fields_json'] !== null) {
                $fields = json_decode($row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
                if (portalIsHidden($hiddenIds, $hiddenProperties, 'mls', (string)$row['source_id'], $fields)) $row['publishable'] = 0;
            }
            unset($row['fields_json']);
        }
        unset($row);
        $hidden = $db->query('SELECT source, source_id, offer_number, title, hidden_at FROM portal_hidden_offers ORDER BY hidden_at DESC, source, source_id')->fetchAll();
        foreach ($hidden as &$row) $row['id'] = portalPublicOfferId((string)$row['source'], (string)$row['source_id']);
        unset($row);
        respond(200, ['authorized' => true, 'email' => $_SESSION['admin_email'], 'csrf' => $_SESSION['csrf'], 'selected' => $rows, 'hidden' => $hidden]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'integration-status') {
        if (!$authorized) respond(401, ['error' => 'login_required']);
        respond(200, integrationStatus(db($config), $config));
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['error' => 'method_not_allowed']);
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== 'https://api.mazurestate.pl' || ($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json') respond(403, ['error' => 'forbidden']);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4000) respond(413, ['error' => 'too_large']);
    $input = json_decode((string)file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input)) respond(400, ['error' => 'invalid_json']);
    $action = (string)($input['action'] ?? '');
    if ($action === 'request-code') {
        $email = strtolower(trim((string)($input['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) respond(422, ['error' => 'invalid_email']);
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipWait = throttle('ip:' . $ip, 10, 3600);
        $emailWait = throttle('email:' . $email, 5, 900);
        if ($ipWait || $emailWait) {
            $retryAfter = max($ipWait, $emailWait);
            header('Retry-After: ' . $retryAfter);
            respond(429, ['error' => 'wait_before_retry', 'retry_after' => $retryAfter]);
        }
        if (in_array($email, $allowed, true)) {
            $code = (string)random_int(100000, 999999);
            $_SESSION['pending_email'] = $email;
            $_SESSION['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
            $_SESSION['code_expires'] = time() + 600;
            $_SESSION['code_attempts'] = 0;
            $subject = 'MazurEstate: kod dostepu do wybranych ofert';
            $body = "Kod dostępu: $code\n\nWażny przez 10 minut. Jeśli nie prosisz o dostęp, zignoruj wiadomość.";
            if (!mail($email, $subject, $body, "From: powiadomienia@mazurestate.pl\r\nContent-Type: text/plain; charset=UTF-8")) throw new RuntimeException('Mail unavailable');
        }
        respond(200, ['ok' => true]);
    }
    if ($action === 'verify-code') {
        $_SESSION['code_attempts'] = (int)($_SESSION['code_attempts'] ?? 0) + 1;
        $code = (string)($input['code'] ?? '');
        $valid = preg_match('/\A[0-9]{6}\z/', $code)
            && (int)($_SESSION['code_expires'] ?? 0) >= time()
            && $_SESSION['code_attempts'] <= 5
            && isset($_SESSION['pending_email'], $_SESSION['code_hash'])
            && in_array($_SESSION['pending_email'], $allowed, true)
            && password_verify($code, $_SESSION['code_hash']);
        if (!$valid) respond(401, ['error' => 'invalid_code']);
        session_regenerate_id(true);
        $_SESSION['admin_email'] = $_SESSION['pending_email'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        unset($_SESSION['pending_email'], $_SESSION['code_hash'], $_SESSION['code_expires'], $_SESSION['code_attempts']);
        respond(200, ['ok' => true]);
    }
    if (!$authorized) respond(401, ['error' => 'login_required']);
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($input['csrf'] ?? ''))) respond(403, ['error' => 'invalid_csrf']);
    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        respond(200, ['ok' => true]);
    }
    if ($action === 'hide' || $action === 'restore') {
        $id = $input['id'] ?? null;
        $parsedId = is_string($id) ? portalParseOfferId($id) : null;
        if ($parsedId === null) respond(422, ['error' => 'invalid_offer']);
        [$source, $sourceId] = $parsedId;
        $db = db($config);
        $hasSource = portalOffersHaveSource($db);
        if ($action === 'restore') {
            $db->prepare('DELETE FROM portal_hidden_offers WHERE source = ? AND source_id = ?')->execute([$source, $sourceId]);
            respond(200, ['ok' => true]);
        }
        if ($source !== 'mls' && !$hasSource) respond(404, ['error' => 'offer_unavailable']);
        $lookup = $db->prepare($hasSource
            ? 'SELECT fields_json FROM mls_offers WHERE source = ? AND source_id = ? AND publishable = 1 LIMIT 1'
            : 'SELECT fields_json FROM mls_offers WHERE source_id = ? AND publishable = 1 LIMIT 1');
        $lookup->execute($hasSource ? [$source, $sourceId] : [$sourceId]);
        $row = $lookup->fetch();
        if (!$row) respond(404, ['error' => 'offer_unavailable']);
        $fields = json_decode($row['fields_json'], true, 512, JSON_THROW_ON_ERROR);
        $propertyKey = portalPropertyKey($fields);
        $number = mb_substr(trim((string)(($fields['numberExport'] ?? '') ?: ($fields['number'] ?? ''))), 0, 80);
        $title = mb_substr(trim((string)($fields['portalTitle'] ?? '')), 0, 255);
        $db->beginTransaction();
        $db->prepare('INSERT INTO portal_hidden_offers (source, source_id, property_key, offer_number, title) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE property_key = VALUES(property_key), offer_number = VALUES(offer_number), title = VALUES(title)')
            ->execute([$source, $sourceId, $propertyKey, $number, $title]);
        $sourceCondition = $hasSource ? "o.source = 'mls' AND " : '';
        $featured = $db->query("SELECT f.source_id, o.fields_json FROM featured_offers f INNER JOIN mls_offers o ON $sourceCondition o.source_id = f.source_id")->fetchAll();
        $remove = $db->prepare('DELETE FROM featured_offers WHERE source_id = ?');
        foreach ($featured as $item) {
            $itemFields = json_decode($item['fields_json'], true, 512, JSON_THROW_ON_ERROR);
            if (($source === 'mls' && $item['source_id'] === $sourceId) || ($propertyKey !== '' && portalPropertyKey($itemFields) === $propertyKey)) {
                $remove->execute([$item['source_id']]);
            }
        }
        $db->commit();
        respond(200, ['ok' => true]);
    }
    if ($action !== 'save') respond(400, ['error' => 'invalid_action']);
    $ids = $input['ids'] ?? null;
    if (!is_array($ids) || !array_is_list($ids) || count($ids) > 10) respond(422, ['error' => 'invalid_selection']);
    foreach ($ids as $id) {
        if (!is_string($id) || !preg_match('/\A[0-9]{1,40}\z/', $id)) respond(422, ['error' => 'invalid_selection']);
    }
    if (count(array_unique($ids)) !== count($ids)) respond(422, ['error' => 'duplicate_selection']);
    $db = db($config);
    $db->beginTransaction();
    [$hiddenIds, $hiddenProperties] = portalHiddenSets($db);
    $sourceCondition = portalOffersHaveSource($db) ? "source = 'mls' AND " : '';
    $check = $db->prepare("SELECT fields_json FROM mls_offers WHERE $sourceCondition source_id = ? AND publishable = 1 LIMIT 1");
    foreach ($ids as $id) {
        $check->execute([$id]);
        $fieldsJson = $check->fetchColumn();
        if (!$fieldsJson || portalIsHidden($hiddenIds, $hiddenProperties, 'mls', $id, json_decode($fieldsJson, true, 512, JSON_THROW_ON_ERROR))) {
            $db->rollBack();
            respond(422, ['error' => 'offer_unavailable', 'id' => $id]);
        }
    }
    $db->exec('DELETE FROM featured_offers');
    $insert = $db->prepare('INSERT INTO featured_offers (source_id, display_order) VALUES (?, ?)');
    foreach ($ids as $position => $id) $insert->execute([$id, $position + 1]);
    $db->commit();
    respond(200, ['ok' => true]);
} catch (JsonException $error) {
    respond(400, ['error' => 'invalid_json']);
} catch (Throwable $error) {
    error_log('Featured admin: ' . $error->getMessage());
    respond(503, ['error' => 'temporarily_unavailable']);
}
