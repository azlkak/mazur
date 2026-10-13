<?php
declare(strict_types=1);
// Independent, CLI-only monitor: it never imports or changes offer data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
require_once __DIR__ . '/../integration-health.php';
require_once __DIR__ . '/../integration-alerts.php';
try { $mode = mazurIntegrationCliMode(array_slice($argv, 1)); }
catch (InvalidArgumentException $error) {
    fwrite(STDERR, "Usage: php check-integrations.php [--test-mail]\n");
    exit(2);
}
$root = dirname(__DIR__);
$result = 0;
try {
    $config = require $root . '/config.php';
    if (!is_array($config)) throw new RuntimeException('Invalid integration configuration');
    $recipients = mazurIntegrationRecipients($config, $root . '/integration-notifications.php');
    if ($recipients === []) {
        fwrite(STDERR, "Integration status notifications are not configured.\n");
        exit(1);
    }
    $send = static fn(string $to, string $subject, string $body): bool => mail($to,
        '=?UTF-8?B?' . base64_encode($subject) . '?=', $body,
        ['From' => 'powiadomienia@mazurestate.pl', 'Content-Type' => 'text/plain; charset=UTF-8']);
    if ($mode === 'test') {
        $summary = mazurSendIntegrationTest($recipients, $send);
        echo 'Integration notification TEST: ' . json_encode($summary, JSON_THROW_ON_ERROR) . "\n";
        exit($summary['failed'] > 0 ? 1 : 0);
    }
    try {
        $db = new PDO($config['dsn'], $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $db->exec("SET time_zone = '+00:00'");
        $snapshot = integrationStatus($db, $config, $root);
        $snapshot['feeds']['monitor'] = ['enabled' => true, 'state' => 'ok'];
    } catch (Throwable $error) {
        // Database exceptions can include DSNs/credentials. Use a fixed incident.
        $snapshot = mazurIntegrationMonitorFailure();
        fwrite(STDERR, "Integration monitor could not read integration status.\n");
        $result = 1;
    }
    $summary = mazurNotifyIntegrationStatus($snapshot, $root . '/runtime/integration-alerts.json', $recipients, $send);
    echo 'Integration status monitor: ' . json_encode($summary, JSON_THROW_ON_ERROR) . "\n";
    if ($summary['failed'] > 0) $result = 1;
} catch (Throwable $error) {
    // Never print raw configuration, database, or delivery exception messages.
    fwrite(STDERR, "Integration status notification check failed; no import was interrupted.\n");
    $result = 1;
}
exit($result);
