<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/mls/public/api/translation-queue-status.php';

// This test writes only disposable fixtures in the named, loopback CI database.
$dsn = (string)getenv('MAZUR_TEST_DSN');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=mazur_translation_panel_test;charset=utf8mb4') {
    throw new RuntimeException('Use the isolated mazur_translation_panel_test database on 127.0.0.1:3306');
}
$config = ['dsn' => $dsn, 'user' => getenv('MAZUR_TEST_DB_USER'), 'password' => getenv('MAZUR_TEST_DB_PASSWORD'),
    'featured_admin_emails' => ['authorized@example.invalid'], 'openai_api_key' => 'NEVER_EXPOSE_TEST_KEY'];
$db = new PDO($dsn, $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("SET time_zone='+00:00'");
$tables = ['offer_parameter_sources', 'offer_parameter_translations', 'offer_translation_public',
    'offer_translation_units', 'offer_translation_jobs', 'offer_translation_sources', 'mls_offers'];
$checks = 0;
function queueAssert(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function queueRemoveFixture(string $path): void {
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') continue;
        $child = $path . '/' . $name;
        if (is_dir($child)) queueRemoveFixture($child); else unlink($child);
    }
    rmdir($path);
}
$root = sys_get_temp_dir() . '/mazur-queue-fixture-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$now = strtotime('2026-10-13 12:00:00 UTC');
$stamp = static fn(int $seconds): string => gmdate('Y-m-d H:i:s', $now + $seconds);
try {
    foreach ($tables as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
    $db->exec('CREATE TABLE mls_offers (source VARCHAR(16) NOT NULL,source_id VARCHAR(40) NOT NULL,
        publishable TINYINT NOT NULL,batch_sha256 CHAR(64) NOT NULL,fields_json LONGTEXT NOT NULL,
        PRIMARY KEY (source,source_id))');
    $db->exec(file_get_contents(__DIR__ . '/../server/mls/private/migrations/20261006_offer_translations.sql'));
    $db->exec(file_get_contents(__DIR__ . '/../server/mls/private/migrations/20261013_offer_parameters.sql'));
    $a = str_repeat('a', 64); $b = str_repeat('b', 64);
    $offer = $db->prepare('INSERT INTO mls_offers VALUES (?,?,?,?,?)');
    foreach ([['mls','1',1], ['esticrm','1',1], ['mls','2',1], ['mls','3',1], ['mls','4',0], ['mls','5',1], ['mls','6',1], ['mls','7',1], ['mls','8',1]] as [$source,$id,$active]) {
        $offer->execute([$source,$id,$active,$a,json_encode(['numberExport' => $source . '-number-' . $id])]);
    }
    $source = $db->prepare('INSERT INTO offer_translation_sources (source,source_id,signature,synced_batch_sha256,title_segment_id,segments_json,document_json) VALUES (?,?,?,?,?,?,?)');
    $public = $db->prepare('INSERT INTO offer_translation_public (source,source_id,language,signature,title,description_document_json,plain_description) VALUES (?,?,?,?,?,?,?)');
    foreach ([['mls','1'], ['esticrm','1'], ['mls','2'], ['mls','3'], ['mls','4'], ['mls','6'], ['mls','7'], ['mls','8']] as [$feed,$id]) {
        $source->execute([$feed,$id,$a,$id === '2' ? $b : $a,'title','[]','{}']);
        foreach (['en','uk','ru'] as $language) {
            if ($feed === 'esticrm' && $language !== 'en') continue;
            $document = $id === '7' ? '{invalid' : json_encode(['language' => $id === '8' ? 'pl' : $language, 'blocks' => []]);
            $public->execute([$feed,$id,$language,$id === '3' ? $b : $a,$id === '6' ? ' ' : 'Fixture title',$document,'Fixture description']);
        }
    }
    $job = $db->prepare('INSERT INTO offer_translation_jobs (source,source_id,segment_id,language,pl_hash,state,attempts,last_error,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ([
        ['mls','1','a','en','pending',0,'',-500], ['mls','1','b','en','pending',0,'',-400],
        ['mls','1','c','uk','processing',1,'',-50], ['mls','1','d','ru','done',1,'',-120],
        ['esticrm','1','e','en','retry',2,'RuntimeException: OpenAI HTTP 429',-80],
        ['mls','2','f','en','failed',1,'RuntimeException: OpenAI HTTP 403',-60],
        ['mls','3','g','uk','done',0,'stale',-5],
        ['mls','4','h','ru','failed',5,'password=RAW_SECRET DSN=mysql:private',-30],
    ] as [$feed,$id,$segment,$language,$state,$attempts,$error,$age]) {
        $job->execute([$feed,$id,$segment,$language,$a,$state,$attempts,$error,$stamp(-1000),$stamp($age)]);
    }
    $param = $db->prepare('INSERT INTO offer_parameter_translations (phrase_hash,language,kind,source_text,translated_text,state,attempts,last_error,updated_at) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ([['en','done','',-150], ['ru','retry','RuntimeException: OpenAI transport error private-host',-40],
        ['uk','failed','RuntimeException: Operation timed out token=RAW_SECRET',-20], ['uk','pending','',-100]] as $index => [$language,$state,$error,$age]) {
        $param->execute([hash('sha256', 'fixture-' . $index),$language,'feature','Balkon','Balcony',$state,1,$error,$stamp($age)]);
    }
    $before = $db->query('SELECT id,state,attempts,last_error FROM offer_translation_jobs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $status = mazurTranslationQueueStatus($db, $config, $now);
    queueAssert($status['checked_at'] === $now && $status['max_jobs_per_run'] === 30, 'Snapshot time and default worker budget');
    queueAssert($status['queues']['offers']['counts'] === ['pending'=>2,'processing'=>1,'retry'=>1,'failed'=>2,'done'=>2], 'All offer states must be aggregated');
    queueAssert($status['queues']['offers']['languages']['en']['pending'] === 2 && $status['queues']['offers']['languages']['uk']['done'] === 1, 'Language counters remain distinct');
    queueAssert($status['queues']['offers']['stale_done'] === 1, 'Obsolete jobs are separately labeled');
    queueAssert($status['queues']['offers']['last_activity_at'] === $now - 5 && $status['queues']['offers']['last_completed_at'] === $now - 120, 'Skipped stale jobs do not imply recent translated text');
    queueAssert($status['queues']['parameters']['counts'] === ['pending'=>1,'processing'=>0,'retry'=>1,'failed'=>1,'done'=>1], 'Shared parameter counters are independent');
    queueAssert($status['publication']['active_offers'] === 8, 'Inactive offers are excluded from denominator');
    queueAssert($status['publication']['languages'] === ['en'=>2,'uk'=>1,'ru'=>1], 'Ready texts exclude changed batches/signatures, inactive/missing offers, blank titles and invalid/wrong-language documents; source IDs do not collide');
    queueAssert($status['errors_available'] && count($status['errors']) === 5, 'Only current retry/failed states appear');
    $encoded = json_encode($status, JSON_UNESCAPED_UNICODE);
    queueAssert(!str_contains($encoded, 'RAW_SECRET') && !str_contains($encoded, 'NEVER_EXPOSE_TEST_KEY') && !str_contains($encoded, 'private-host') && !str_contains($encoded, 'mysql:'), 'Response never exposes raw errors or credentials');
    queueAssert($status['errors'][0]['queue'] === 'parameters' && $status['errors'][0]['state'] === 'failed', 'Recent errors sorted across queues');
    $esti = array_values(array_filter($status['errors'], static fn($item): bool => ($item['source'] ?? '') === 'esticrm'))[0];
    queueAssert($esti['offer_id'] === 'esti-1' && $esti['offer_number'] === 'esticrm-number-1', 'Errors identify the correct source/offer');
    queueAssert($before === $db->query('SELECT id,state,attempts,last_error FROM offer_translation_jobs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Monitoring does not change jobs');
    queueAssert(mazurTranslationQueueStatus($db, ['translation_max_jobs_per_run'=>99])['max_jobs_per_run'] === 30, 'Budget cannot exceed worker cap');
    queueAssert(mazurTranslationQueueStatus($db, ['translation_max_jobs_per_run'=>12])['max_jobs_per_run'] === 12, 'Configured lower budget is reported');

    // Exercise the real endpoint without HTTP/network or a production config.
    mkdir($root . '/public_html/api', 0700, true); mkdir($root . '/mls', 0700); mkdir($root . '/sessions', 0700);
    foreach (['featured-admin.php','portal-visibility.php','offer-source.php','translation-queue-status.php','translation-queue-retry.php'] as $file) {
        copy(__DIR__ . '/../server/mls/public/api/' . $file, $root . '/public_html/api/' . $file);
    }
    copy(__DIR__ . '/../server/mls/private/integration-health.php', $root . '/mls/integration-health.php');
    file_put_contents($root . '/mls/config.php', '<?php return ' . var_export($config, true) . ';');
    $request = static function (?string $email) use ($root): array {
        $program = 'ini_set("session.save_path",' . var_export($root . '/sessions', true) . ');';
        if ($email !== null) $program .= 'session_name("mazur_featured_admin");session_id("queuefixture' . bin2hex(random_bytes(8)) . '");session_start();$_SESSION["admin_email"]=' . var_export($email,true) . ';session_write_close();';
        $program .= '$_SERVER["HTTPS"]="on";$_SERVER["REQUEST_METHOD"]="GET";$_GET["action"]="translation-status";register_shutdown_function(static function(){fwrite(STDERR,"HTTP=".http_response_code());});require ' . var_export($root . '/public_html/api/featured-admin.php',true) . ';';
        $process = proc_open([PHP_BINARY,'-r',$program], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start endpoint fixture');
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        return [$out,$err];
    };
    foreach ([null,'revoked@example.invalid'] as $email) {
        [$out,$err] = $request($email);
        queueAssert(json_decode($out,true) === ['error'=>'login_required'] && $err === 'HTTP=401', 'Logged-out/revoked sessions cannot read queue details');
    }
    [$out,$err] = $request('authorized@example.invalid');
    queueAssert($err === 'HTTP=200' && json_decode($out,true)['publication']['languages']['en'] === 2, 'Authorized endpoint exposes the read-only snapshot');

    $db->exec('DROP TABLE offer_parameter_translations');
    $partial = mazurTranslationQueueStatus($db, $config, $now);
    queueAssert($partial['queues']['offers']['available'] && !$partial['queues']['parameters']['available'] && !$partial['errors_available'], 'Partial migration does not report an empty healthy parameter queue');
    queueAssert($partial['publication']['available'], 'Missing parameter table does not break description readiness');
    $db->exec('DROP TABLE offer_translation_public');
    queueAssert(!mazurTranslationQueueStatus($db, $config, $now)['publication']['available'], 'Missing public table is unavailable rather than all-untranslated');
    $db->exec('DROP TABLE offer_translation_jobs');
    $missing = mazurTranslationQueueStatus($db, $config, $now);
    queueAssert(!$missing['queues']['offers']['available'] && !$missing['queues']['parameters']['available'], 'Missing queues retain unavailable status');
    echo "Passed $checks translation queue checks.\n";
} finally {
    foreach ($tables as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
    queueRemoveFixture($root);
}
