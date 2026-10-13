<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/mls/public/api/translation-queue-retry.php';

// All mutation tests are confined to this disposable loopback CI database.
$dsn = (string)getenv('MAZUR_TEST_DSN');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=mazur_translation_panel_test;charset=utf8mb4') {
    throw new RuntimeException('Use the isolated mazur_translation_panel_test database on 127.0.0.1:3306');
}
$config = ['dsn' => $dsn, 'user' => getenv('MAZUR_TEST_DB_USER'), 'password' => getenv('MAZUR_TEST_DB_PASSWORD'),
    'featured_admin_emails' => ['authorized@example.invalid'], 'openai_api_key' => 'NEVER_EXPOSE_RETRY_TEST_KEY'];
$connect = static fn(): PDO => new PDO($dsn, $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = $connect(); $holder = $connect();
$db->exec("SET time_zone='+00:00'");
$tables = ['offer_parameter_sources', 'offer_parameter_translations', 'offer_translation_public',
    'offer_translation_units', 'offer_translation_jobs', 'offer_translation_sources', 'mls_offers'];
$checks = 0;
function retryAssert(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function retryRemoveFixture(string $path): void {
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') continue;
        $child = $path . '/' . $name;
        if (is_dir($child)) retryRemoveFixture($child); else unlink($child);
    }
    rmdir($path);
}
$root = sys_get_temp_dir() . '/mazur-retry-fixture-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$server = null;
$offerSnapshot = static fn(): array => $db->query('SELECT * FROM offer_translation_jobs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$parameterSnapshot = static fn(): array => $db->query('SELECT * FROM offer_parameter_translations ORDER BY phrase_hash,language')->fetchAll(PDO::FETCH_ASSOC);
$assertUnlocked = static function () use ($holder): void {
    retryAssert((int)$holder->query("SELECT GET_LOCK('mazur_offer_translations',0)")->fetchColumn() === 1, 'Retry releases the shared worker lock');
    $holder->query("SELECT RELEASE_LOCK('mazur_offer_translations')")->closeCursor();
};
try {
    foreach ($tables as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
    $db->exec('CREATE TABLE mls_offers (source VARCHAR(16) NOT NULL,source_id VARCHAR(40) NOT NULL,
        publishable TINYINT NOT NULL,batch_sha256 CHAR(64) NOT NULL,fields_json LONGTEXT NOT NULL,
        PRIMARY KEY (source,source_id))');
    $db->exec(file_get_contents(__DIR__ . '/../server/mls/private/migrations/20261006_offer_translations.sql'));
    $db->exec(file_get_contents(__DIR__ . '/../server/mls/private/migrations/20261013_offer_parameters.sql'));
    $job = $db->prepare('INSERT INTO offer_translation_jobs (source,source_id,segment_id,language,pl_hash,state,attempts,last_error,next_attempt_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ([
        ['1','en','pending',2,'pending-context'], ['2','uk','processing',3,'processing-context'],
        ['3','ru','retry',4,'RuntimeException: OpenAI HTTP 429'], ['4','en','done',1,''],
        ['5','uk','done',0,'stale'], ['6','en','failed',5,'RAW_SECRET failed-en'],
        ['7','uk','failed',1,'RAW_SECRET failed-uk'], ['8','de','failed',5,'unsupported-language'],
    ] as [$id,$language,$state,$attempts,$error]) {
        $job->execute(['mls',$id,'title',$language,str_repeat('a',64),$state,$attempts,$error,'2099-01-01 00:00:00','2026-01-01 00:00:00','2026-01-01 00:00:00']);
    }
    $param = $db->prepare('INSERT INTO offer_parameter_translations (phrase_hash,language,kind,source_text,translated_text,state,attempts,last_error,next_attempt_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ([['en','failed',5,'RAW_SECRET parameter'], ['uk','done',1,''], ['ru','retry',2,'transport retry'], ['de','failed',5,'unsupported-language']] as $index => [$language,$state,$attempts,$error]) {
        $param->execute([hash('sha256','fixture-' . $index),$language,'feature','Balkon','Balcony',$state,$attempts,$error,'2099-01-01 00:00:00','2026-01-01 00:00:00']);
    }
    $before = $offerSnapshot(); $parametersBefore = $parameterSnapshot();
    foreach ([['unknown',2], ['offer_translation_jobs',2], ['offers; DROP TABLE offer_translation_jobs',2], ['offers',-1]] as [$queue,$expected]) {
        $invalid = false;
        try { mazurRetryFailedTranslations($db,$queue,$expected); } catch (InvalidArgumentException) { $invalid = true; }
        retryAssert($invalid, 'Invalid queues/counts must be rejected before SQL mutations');
    }
    retryAssert($offerSnapshot() === $before && $parameterSnapshot() === $parametersBefore, 'Invalid requests leave both queues untouched');
    $mismatch = mazurRetryFailedTranslations($db,'offers',1);
    retryAssert($mismatch === ['ok'=>false,'error'=>'queue_changed','failed'=>2], 'Stale confirmation returns the current failed count');
    retryAssert($offerSnapshot() === $before && $parameterSnapshot() === $parametersBefore, 'Count mismatch does not reset any job');
    $assertUnlocked();

    retryAssert((int)$holder->query("SELECT GET_LOCK('mazur_offer_translations',0)")->fetchColumn() === 1, 'Fixture holds the real worker advisory lock');
    try {
        retryAssert(mazurRetryFailedTranslations($db,'offers',2) === ['ok'=>false,'error'=>'translation_busy'], 'Retry refuses to overlap an active worker');
        retryAssert($offerSnapshot() === $before && $parameterSnapshot() === $parametersBefore, 'Busy worker leaves both queues untouched');
    } finally { $holder->query("SELECT RELEASE_LOCK('mazur_offer_translations')")->closeCursor(); }
    $from = (int)$db->query('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP())')->fetchColumn();
    retryAssert(mazurRetryFailedTranslations($db,'offers',2) === ['ok'=>true,'queue'=>'offers','requeued'=>2], 'Exactly the confirmed failed offer jobs are requeued');
    $until = (int)$db->query('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP())')->fetchColumn();
    $after = $offerSnapshot();
    foreach ($before as $index => $old) {
        $row = $after[$index];
        if ($old['state'] !== 'failed' || !in_array($old['language'],['en','uk','ru'],true)) {
            retryAssert($row === $old, 'Pending/processing/retry/done/stale/other-language jobs are preserved exactly');
        } else {
            $attemptAt = strtotime($row['next_attempt_at'] . ' UTC');
            retryAssert($row['state'] === 'pending' && (int)$row['attempts'] === 0 && $row['last_error'] === '' && $attemptAt >= $from && $attemptAt <= $until, 'Failed jobs receive a clean immediately-due retry');
            foreach (['id','source','source_id','segment_id','language','pl_hash','created_at'] as $field) retryAssert($row[$field] === $old[$field], 'Retry preserves identity and original creation time');
        }
    }
    retryAssert($parameterSnapshot() === $parametersBefore, 'Retrying descriptions cannot change shared parameter jobs');
    retryAssert(mazurRetryFailedTranslations($db,'offers',2) === ['ok'=>false,'error'=>'queue_changed','failed'=>0], 'Repeating the old confirmation cannot reset already-pending jobs');
    retryAssert(mazurRetryFailedTranslations($db,'offers',0) === ['ok'=>true,'queue'=>'offers','requeued'=>0] && $offerSnapshot() === $after, 'An empty confirmed queue is idempotent');
    $assertUnlocked();
    retryAssert(mazurRetryFailedTranslations($db,'parameters',1) === ['ok'=>true,'queue'=>'parameters','requeued'=>1], 'Parameter retry is independently selectable');
    foreach ($parameterSnapshot() as $index => $row) {
        $old = $parametersBefore[$index];
        if ($old['state'] === 'failed' && in_array($old['language'],['en','uk','ru'],true)) {
            retryAssert($row['state'] === 'pending' && (int)$row['attempts'] === 0 && $row['last_error'] === '' && $row['translated_text'] === $old['translated_text'], 'Parameter retry clears only scheduling/error state');
        } else retryAssert($row === $old, 'Other parameter states/languages retain all values');
    }
    retryAssert($offerSnapshot() === $after, 'Parameter retry does not touch the offer queue');
    $assertUnlocked();

    // An update failure must roll the transaction back and release the worker lock.
    $db->exec("UPDATE offer_translation_jobs SET state='failed',attempts=5,last_error='rollback-marker' WHERE source_id IN ('6','7')");
    $rollbackBefore = $offerSnapshot();
    $db->exec("CREATE TRIGGER retry_fixture_abort BEFORE UPDATE ON offer_translation_jobs FOR EACH ROW BEGIN IF OLD.last_error='rollback-marker' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture RAW_SECRET rollback'; END IF; END");
    $rolledBack = false;
    try { mazurRetryFailedTranslations($db,'offers',2); } catch (Throwable) { $rolledBack = true; }
    retryAssert($rolledBack && !$db->inTransaction() && $offerSnapshot() === $rollbackBefore, 'An update error leaves no partial reset or open transaction');
    $db->exec('DROP TRIGGER retry_fixture_abort');
    $assertUnlocked();

    // Exercise the actual POST endpoint against an isolated local HTTP server.
    mkdir($root . '/public_html/api',0700,true); mkdir($root . '/mls',0700); mkdir($root . '/sessions',0700);
    foreach (['featured-admin.php','portal-visibility.php','offer-source.php','translation-queue-status.php','translation-queue-retry.php'] as $file) {
        copy(__DIR__ . '/../server/mls/public/api/' . $file,$root . '/public_html/api/' . $file);
    }
    copy(__DIR__ . '/../server/mls/private/integration-health.php',$root . '/mls/integration-health.php');
    file_put_contents($root . '/mls/config.php','<?php return ' . var_export($config,true) . ';');
    file_put_contents($root . '/router.php','<?php $_SERVER["HTTPS"]="on"; require __DIR__ . "/public_html/api/featured-admin.php";');
    $socket = stream_socket_server('tcp://127.0.0.1:0',$socketError,$socketMessage);
    if ($socket === false) throw new RuntimeException('Cannot allocate a loopback test port');
    $address = stream_socket_get_name($socket,false); fclose($socket);
    $server = proc_open([PHP_BINARY,'-d','session.save_path=' . $root . '/sessions','-d','session.serialize_handler=php','-d','session.save_handler=files','-d','display_errors=0','-S',$address,$root . '/router.php'],
        [0=>['file','/dev/null','r'],1=>['file',$root . '/server.log','a'],2=>['file',$root . '/server.log','a']],$serverPipes);
    if (!is_resource($server)) throw new RuntimeException('Cannot start the local endpoint fixture');
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address,$probeError,$probeMessage,0.1);
        if ($probe !== false) { fclose($probe); $ready = true; break; }
        if (!proc_get_status($server)['running']) break;
        usleep(20000);
    }
    if (!$ready) throw new RuntimeException('Local endpoint fixture did not start');
    $session = static function (?string $email, ?string $csrf) use ($root): ?string {
        if ($email === null) return null;
        $id = 'retryfixture' . bin2hex(random_bytes(8));
        $content = 'admin_email|' . serialize($email);
        if ($csrf !== null) $content .= 'csrf|' . serialize($csrf);
        file_put_contents($root . '/sessions/sess_' . $id,$content);
        return $id;
    };
    $validSession = $session('authorized@example.invalid','fixture-csrf');
    $request = static function (array $payload, ?string $sessionId, string $origin = 'https://api.mazurestate.pl', string $method = 'POST', string $type = 'application/json') use ($address): array {
        $headers = ['Content-Type: ' . $type,'Origin: ' . $origin];
        if ($sessionId !== null) $headers[] = 'Cookie: mazur_featured_admin=' . $sessionId;
        $options = ['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true,'timeout'=>5];
        if ($method === 'POST') $options['content'] = json_encode($payload,JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $url = 'http://' . $address . '/api/featured-admin.php' . ($method === 'GET' ? '?action=translation-status' : '');
        $out = file_get_contents($url,false,stream_context_create(['http'=>$options]));
        if ($out === false) throw new RuntimeException('Local endpoint request failed');
        preg_match('/\AHTTP\/\S+ (\d+)/',$http_response_header[0] ?? '',$status);
        return [(int)($status[1] ?? 0),json_decode($out,true,512,JSON_THROW_ON_ERROR),$out];
    };
    $payload = ['action'=>'retry-translations','queue'=>'offers','expected_failed'=>2,'confirmed'=>true,'csrf'=>'fixture-csrf'];
    foreach ([null,$session('revoked@example.invalid','fixture-csrf')] as $id) {
        [$code,$body] = $request($payload,$id);
        retryAssert($code === 401 && $body === ['error'=>'login_required'], 'Only an allowlisted session may retry');
    }
    foreach (['https://evil.example.invalid','https://api.mazurestate.pl.evil.example.invalid','http://api.mazurestate.pl',''] as $origin) {
        [$code,$body] = $request($payload,$validSession,$origin);
        retryAssert($code === 403 && $body === ['error'=>'forbidden'], 'Retry requires the exact trusted origin');
    }
    [$code,$body] = $request($payload,$validSession,'https://api.mazurestate.pl','POST','text/plain');
    retryAssert($code === 403 && $body === ['error'=>'forbidden'], 'Retry accepts JSON content type only');
    foreach (['','wrong'] as $csrf) {
        [$code,$body] = $request(array_replace($payload,['csrf'=>$csrf]),$validSession);
        retryAssert($code === 403 && $body === ['error'=>'invalid_csrf'], 'Missing/incorrect CSRF cannot retry');
    }
    foreach ([null,''] as $csrf) {
        [$code,$body] = $request(array_replace($payload,['csrf'=>'']),$session('authorized@example.invalid',$csrf));
        retryAssert($code === 403 && $body === ['error'=>'invalid_csrf'], 'Empty session and request tokens are never accepted');
    }
    foreach ([['confirmed'=>false],['confirmed'=>1],['confirmed'=>'true'],['queue'=>'unknown'],['queue'=>['offers']],['expected_failed'=>'2'],['expected_failed'=>-1],['expected_failed'=>2.0],['expected_failed'=>null],['expected_failed'=>true]] as $invalid) {
        [$code,$body] = $request(array_replace($payload,$invalid),$validSession);
        retryAssert($code === 422 && $body === ['error'=>'invalid_retry_request'], 'Retry requires explicit confirmation, a whitelisted queue and a nonnegative integer count');
    }
    $withoutConfirmation = $payload; unset($withoutConfirmation['confirmed']);
    [$code,$body] = $request($withoutConfirmation,$validSession);
    retryAssert($code === 422 && $body === ['error'=>'invalid_retry_request'], 'Absent explicit confirmation is rejected');
    retryAssert($offerSnapshot() === $rollbackBefore, 'All rejected endpoint requests preserve queue state');
    [$code,$body] = $request(array_replace($payload,['expected_failed'=>1]),$validSession);
    retryAssert($code === 409 && $body === ['ok'=>false,'error'=>'queue_changed','failed'=>2], 'Endpoint reports stale count as a conflict');
    $holder->query("SELECT GET_LOCK('mazur_offer_translations',0)")->closeCursor();
    try {
        [$code,$body] = $request($payload,$validSession);
        retryAssert($code === 409 && $body === ['ok'=>false,'error'=>'translation_busy'], 'Endpoint reports active worker as a conflict');
    } finally { $holder->query("SELECT RELEASE_LOCK('mazur_offer_translations')")->closeCursor(); }
    [$code,$body] = $request([], $validSession,'https://api.mazurestate.pl','GET');
    retryAssert($code === 200 && isset($body['queues']) && $offerSnapshot() === $rollbackBefore, 'GET monitoring remains read-only after retry support');
    [$code,$body] = $request($payload,$validSession);
    retryAssert($code === 200 && $body === ['ok'=>true,'queue'=>'offers','requeued'=>2], 'Authorized confirmed POST resets failed jobs only');
    $endpointAfter = $offerSnapshot();
    [$code,$body] = $request($payload,$validSession);
    retryAssert($code === 409 && $body['error'] === 'queue_changed' && $offerSnapshot() === $endpointAfter, 'Repeated POST does not requeue previously-reset jobs');

    $db->exec('DROP TABLE offer_parameter_translations');
    [$code,$body,$raw] = $request(array_replace($payload,['queue'=>'parameters','expected_failed'=>0]),$validSession);
    retryAssert($code === 503 && $body === ['error'=>'temporarily_unavailable'] && !str_contains($raw,'RAW_SECRET') && !str_contains($raw,'NEVER_EXPOSE_RETRY_TEST_KEY') && !str_contains($raw,'mysql:'), 'Missing schema is safely unavailable without leaking details');
    $assertUnlocked();
    echo "Passed $checks translation retry checks.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($db->inTransaction()) $db->rollBack();
    $db->query("SELECT RELEASE_LOCK('mazur_offer_translations')")->closeCursor();
    $holder->query("SELECT RELEASE_LOCK('mazur_offer_translations')")->closeCursor();
    foreach ($tables as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
    retryRemoveFixture($root);
}
