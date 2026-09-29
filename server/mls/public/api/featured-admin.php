<?php
declare(strict_types=1);

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
        $rows = db($config)->query('SELECT f.source_id, o.publishable FROM featured_offers f LEFT JOIN mls_offers o ON o.source_id = f.source_id ORDER BY f.display_order')->fetchAll();
        respond(200, ['authorized' => true, 'email' => $_SESSION['admin_email'], 'csrf' => $_SESSION['csrf'], 'selected' => $rows]);
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
    if ($action !== 'save') respond(400, ['error' => 'invalid_action']);
    $ids = $input['ids'] ?? null;
    if (!is_array($ids) || !array_is_list($ids) || count($ids) > 10) respond(422, ['error' => 'invalid_selection']);
    foreach ($ids as $id) {
        if (!is_string($id) || !preg_match('/\A[0-9]{1,40}\z/', $id)) respond(422, ['error' => 'invalid_selection']);
    }
    if (count(array_unique($ids)) !== count($ids)) respond(422, ['error' => 'duplicate_selection']);
    $db = db($config);
    $db->beginTransaction();
    $check = $db->prepare('SELECT 1 FROM mls_offers WHERE source_id = ? AND publishable = 1 LIMIT 1');
    foreach ($ids as $id) {
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
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
