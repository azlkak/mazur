<?php
declare(strict_types=1);
// Disposable filesystem, PDO fixtures and injected delivery: no production DB/mail.
require_once __DIR__ . '/../server/mls/private/integration-health.php';
require_once __DIR__ . '/../server/mls/private/integration-alerts.php';
$checks = 0;
function assertAlert(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function removeAlertFixtures(string $path): void {
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') continue;
        $child = $path . '/' . $name;
        if (is_dir($child)) removeAlertFixtures($child); else unlink($child);
    }
    rmdir($path);
}
class AlertFixtureStatement extends PDOStatement {
    private array $rows;
    public function __construct(private AlertFixtureDb $db, private string $query) { $this->rows = $db->rows($query, []); }
    public function execute(?array $params = null): bool { $this->rows = $this->db->rows($this->query, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
class AlertFixtureDb extends PDO {
    public function __construct(public int $now, public bool $hasSource = true) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { return new AlertFixtureStatement($this, $query); }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new AlertFixtureStatement($this, $query); }
    public function rows(string $query, array $params): array {
        if (str_starts_with($query, 'SHOW COLUMNS')) return $this->hasSource ? [['Field' => 'source']] : [];
        if (str_contains($query, 'COUNT(*)')) return [['total' => '9', 'publishable' => '7']];
        $source = $params[0] ?? (str_contains($query, "source='vixcrm'") ? 'vixcrm' : 'mls');
        return [['file_name' => 'test.zip', 'export_type' => 'incremental', 'offer_count' => '3',
            'imported_at' => $source === 'vixcrm' ? $this->now - 86400 * 20 : $this->now - 60]];
    }
}

$root = sys_get_temp_dir() . '/mazur-alert-tests-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$now = 1800000000;
$health = ['result' => 'success', 'last_run_at' => $now - 60];
$counts = ['incoming' => 0, 'processing' => 0, 'errors' => 0];
$recent = [['imported_at' => $now - 60]];
$recipients = ['one@example.invalid', 'two@example.invalid'];
$mail = [];
$send = function($to, $subject, $body) use (&$mail): bool { $mail[] = [$to, $subject, $body]; return true; };
$lock = static fn($handle): bool => true;
$feed = static fn(string $state, string $reason = '', bool $enabled = true): array => [
    'enabled' => $enabled, 'state' => $state, 'state_reason' => $reason,
    'last_run_at' => $now - 60, 'batches' => [['imported_at' => $now - 60]],
    'incoming' => 4, 'processing' => 0, 'errors' => 0,
];
$notify = function(string $name, array $feeds, int $at, ?callable $delivery = null, ?callable $acquire = null) use ($root, $recipients, $send, $lock): array {
    return mazurNotifyIntegrationStatus(['feeds' => $feeds], $root . '/' . $name . '.json', $recipients,
        $delivery ?? $send, $at, $acquire ?? $lock);
};
try {
    assertAlert(mazurIntegrationState('mls', true, $health, $counts, [], $recent, $now) === ['ok', ''], 'Fresh importer must be healthy');
    assertAlert(mazurIntegrationState('mls', false, [], ['errors' => 2], [], [], $now) === ['disabled', ''], 'Disabled feed does not alert on old artifacts');
    assertAlert(mazurIntegrationState('mls', true, [], $counts, [], $recent, $now) === ['unknown', ''], 'Missing telemetry must be unknown');
    assertAlert(mazurIntegrationState('mls', true, ['result' => 'unexpected', 'last_run_at' => $now], $counts, [], $recent, $now)[0] === 'unknown', 'Invalid telemetry must not be falsely healthy');
    assertAlert(mazurIntegrationState('mls', true, $health, ['errors' => 1], [], $recent, $now)[0] === 'error', 'Quarantined packages remain unhealthy');
    assertAlert(mazurIntegrationState('mls', true, ['result' => 'error', 'last_run_at' => $now], $counts, [], $recent, $now)[0] === 'error', 'Importer failure must be unhealthy');
    foreach ([['run', 8100], ['incoming', 8100], ['processing', 5400]] as [$reason, $limit]) {
        $h = $health; $oldest = [];
        if ($reason === 'run') $h['last_run_at'] = $now - $limit;
        else $oldest[$reason] = $now - $limit;
        assertAlert(mazurIntegrationState('mls', true, $h, $counts, $oldest, $recent, $now)[0] === 'ok', 'Exact threshold must not alert: ' . $reason);
        if ($reason === 'run') $h['last_run_at']--;
        else $oldest[$reason]--;
        assertAlert(mazurIntegrationState('mls', true, $h, $counts, $oldest, $recent, $now) === ['stale', $reason], 'Exceeded threshold must explain reason: ' . $reason);
    }
    assertAlert(mazurIntegrationState('mls', true, ['result' => 'running', 'last_run_at' => $now - 5400], $counts, [], $recent, $now)[0] === 'running', 'Import at 90 minutes remains running');
    assertAlert(mazurIntegrationState('mls', true, ['result' => 'running', 'last_run_at' => $now - 5401], $counts, [], $recent, $now) === ['stale', 'run'], 'Stuck import alerts after 90 minutes');
    assertAlert(mazurIntegrationState('mls', true, $health, $counts, [], [['imported_at' => $now - 10800]], $now)[0] === 'ok', 'Quiet threshold is strict');
    assertAlert(mazurIntegrationState('mls', true, $health, $counts, [], [['imported_at' => $now - 10801]], $now)[0] === 'quiet', 'Missing MLS packages alert after three hours');
    assertAlert(mazurIntegrationState('esticrm', true, $health, $counts, [], [], $now)[0] === 'ok', 'Esti does not require hourly new packages');

    $notify('transitions', ['mls' => $feed('ok')], $now);
    $notify('transitions', ['mls' => $feed('running')], $now + 1);
    assertAlert(count($mail) === 0, 'Healthy/running states must not send');
    $notify('transitions', ['mls' => $feed('error')], $now + 2);
    assertAlert(count($mail) === 2, 'A failed incident reaches both recipients');
    $notify('transitions', ['mls' => $feed('error')], $now + 3600);
    $notify('transitions', ['mls' => $feed('running')], $now + 3601);
    $notify('transitions', ['mls' => $feed('error')], $now + 3602);
    assertAlert(count($mail) === 2, 'Retries and running transitions must not resend same failure');
    $notify('transitions', ['mls' => $feed('ok')], $now + 3603);
    $notify('transitions', ['mls' => $feed('error')], $now + 3604);
    assertAlert(count($mail) === 4, 'New failure after verified recovery alerts again');
    $notify('transitions', ['mls' => $feed('stale', 'incoming')], $now + 3605);
    $notify('transitions', ['mls' => $feed('stale', 'processing')], $now + 3606);
    assertAlert(count($mail) === 8, 'A different state/reason is a distinct incident');
    foreach (['quiet', 'unknown'] as $state) {
        $notify($state, ['mls' => $feed($state)], $now);
        $notify($state, ['mls' => $feed($state)], $now + 600);
    }
    assertAlert(count($mail) === 12, 'Quiet/unknown alerts deduplicate');
    $notify('feeds', ['mls' => $feed('error'), 'esticrm' => $feed('error')], $now);
    assertAlert(count($mail) === 16, 'Each source has independent incident');

    $attempts = []; $failSecond = true;
    $partialSend = function($to, $subject, $body) use (&$attempts, &$failSecond): bool {
        $attempts[] = $to;
        return $to !== 'two@example.invalid' || !$failSecond;
    };
    $summary = $notify('partial', ['mls' => $feed('error')], $now, $partialSend);
    assertAlert($summary['sent'] === 1 && $summary['failed'] === 1, 'Partial failure is recorded per recipient');
    $notify('partial', ['mls' => $feed('error')], $now + 299, $partialSend);
    assertAlert(count($attempts) === 2, 'Failed delivery has five minute cooldown');
    $failSecond = false;
    $notify('partial', ['mls' => $feed('error')], $now + 300, $partialSend);
    assertAlert($attempts === [$recipients[0], $recipients[1], $recipients[1]], 'Only undelivered recipient retries');
    $notify('partial', ['mls' => $feed('error')], $now + 900, $partialSend);
    assertAlert(count($attempts) === 3, 'Successful retry persists dedup across checks');
    $state = json_decode(file_get_contents($root . '/partial.json'), true, 32, JSON_THROW_ON_ERROR);
    assertAlert(count($state['feeds']['mls']['recipients']) === 2 && !str_contains(file_get_contents($root . '/partial.json'), '@'), 'Private state tracks hashed recipient keys');

    $before = count($mail);
    $notify('vix', ['vixcrm' => $feed('disabled', '', false)], $now);
    $notify('vix', ['vixcrm' => $feed('unknown')], $now + 1);
    $notify('vix', ['vixcrm' => $feed('stale', 'run')], $now + 2);
    assertAlert(count($mail) === $before, 'One-time Vix creates no age/disabled/unknown alerts');
    $notify('vix', ['vixcrm' => $feed('error')], $now + 3);
    assertAlert(count($mail) === $before + 2, 'Enabled Vix failures still alert');
    $summary = $notify('busy', ['mls' => $feed('error')], $now, null, static fn($handle): bool => false);
    assertAlert($summary['busy'] && !is_file($root . '/busy.json'), 'Concurrent monitor skips without sending or changing state');

    $failure = mazurIntegrationMonitorFailure();
    $before = count($mail);
    $notify('monitor', $failure['feeds'], $now);
    $notify('monitor', $failure['feeds'], $now + 600);
    assertAlert(count($mail) === $before + 2, 'Database/read failure is one stable monitor incident');
    $notify('monitor', ['monitor' => $feed('ok')], $now + 601);
    $notify('monitor', $failure['feeds'], $now + 602);
    assertAlert(count($mail) === $before + 4, 'Fresh monitor failure after recovery alerts again');
    $unsafeFeed = $feed('error'); $unsafeFeed['last_error'] = 'password=SUPER_SECRET DSN=mysql:secret';
    [$subject, $body] = mazurIntegrationAlertMessage('mls', $unsafeFeed);
    assertAlert(!str_contains($body . $subject, 'SUPER_SECRET') && !str_contains($body, 'DSN='), 'Mail body does not disclose raw errors');
    assertAlert(str_contains($body, 'Europe/Warsaw') === false && str_contains($body, 'Panel: https://api.mazurestate.pl'), 'Mail gives operational details and panel link');
    assertAlert(mazurIntegrationRecipients(['integration_notification_emails' => ['ONE@example.invalid', 'one@example.invalid']]) === ['one@example.invalid'], 'Recipient dedup is case insensitive');
    foreach ([['bad'], ["one@example.invalid\r\nBcc: secret@example.invalid"], 'not an array'] as $bad) {
        $rejected = false;
        try { mazurIntegrationRecipients(['integration_notification_emails' => $bad]); } catch (RuntimeException $e) { $rejected = true; }
        assertAlert($rejected, 'Invalid recipient configuration must be rejected');
    }
    $recipientFile = $root . '/recipients.php';
    $fallbackConfig = ['integration_notification_emails' => ['fallback@example.invalid']];
    assertAlert(mazurIntegrationRecipients($fallbackConfig, $recipientFile) === ['fallback@example.invalid'], 'Missing override retains main config recipients');
    file_put_contents($recipientFile, "<?php return ['emails' => ['override@example.invalid']];");
    assertAlert(mazurIntegrationRecipients($fallbackConfig, $recipientFile) === ['override@example.invalid'], 'Private override takes precedence');
    file_put_contents($recipientFile, "<?php return ['emails' => []];");
    assertAlert(mazurIntegrationRecipients($fallbackConfig, $recipientFile) === [], 'Empty override intentionally disables recipients');
    foreach (["<?php return [];", "<?php return ['emails' => 'bad'];", "<?php return ['emails' => ['invalid']];", str_repeat(' ', 4097)] as $bad) {
        file_put_contents($recipientFile, $bad); clearstatcache(true, $recipientFile);
        $rejected = false;
        try { mazurIntegrationRecipients($fallbackConfig, $recipientFile); } catch (RuntimeException $error) { $rejected = true; }
        assertAlert($rejected, 'Private recipient file validates schema, addresses and size');
    }
    file_put_contents($root . '/corrupt.json', '{bad');
    $before = count($mail); $failed = false;
    try { $notify('corrupt', ['mls' => $feed('error')], $now); } catch (JsonException $error) { $failed = true; }
    assertAlert($failed && count($mail) === $before, 'Corrupt state is preserved, not reset into repeated alerts');

    mkdir($root . '/logs'); mkdir($root . '/esticrm');
    foreach (['incoming', 'processing', 'errors'] as $directory) {
        mkdir($root . '/' . $directory); mkdir($root . '/esticrm/' . $directory);
    }
    foreach (['mls', 'esticrm', 'vixcrm'] as $source) file_put_contents($root . '/logs/status-' . $source . '.json', json_encode($health));
    $config = ['enabled' => true, 'esticrm_import_enabled' => true, 'vixcrm_import_enabled' => true];
    $snapshot = integrationStatus(new AlertFixtureDb($now), $config, $root, $now);
    assertAlert($snapshot['checked_at'] === $now && count($snapshot['feeds']) === 3, 'Shared snapshot works with injected database and time');
    assertAlert($snapshot['feeds']['mls']['state'] === 'ok' && $snapshot['feeds']['esticrm']['state'] === 'ok', 'Panel/monitor share healthy source status');
    assertAlert($snapshot['feeds']['vixcrm']['state'] === 'ok', 'Old one-time Vix snapshot remains healthy');
    file_put_contents($root . '/incoming/waiting.zip', 'fixture'); touch($root . '/incoming/waiting.zip', $now - 8101);
    $snapshot = integrationStatus(new AlertFixtureDb($now), $config, $root, $now);
    assertAlert($snapshot['feeds']['mls']['state_reason'] === 'incoming' && $snapshot['feeds']['mls']['incoming'] === 1, 'Snapshot notices overdue package');
    file_put_contents($root . '/logs/status-esticrm.json', json_encode(['result' => 'success', 'last_run_at' => $now - 8101]));
    $snapshot = integrationStatus(new AlertFixtureDb($now), $config, $root, $now);
    assertAlert($snapshot['feeds']['esticrm']['state'] === 'stale', 'Independent monitor detects missed cron without executing importer');
    $snapshot = integrationStatus(new AlertFixtureDb($now, false), $config, $root, $now);
    assertAlert($snapshot['feeds']['esticrm']['offers_total'] === 0 && !isset($snapshot['feeds']['vixcrm']), 'Legacy source-less schema behavior remains compatible');
    $mail = [];
    $beforeFiles = scandir($root);
    $summary = mazurSendIntegrationTest($recipients, $send);
    assertAlert($summary === ['sent' => 2, 'failed' => 0] && count($mail) === 2, 'Explicit test sends once to each recipient');
    assertAlert(str_contains($mail[0][1], 'TEST') && str_contains($mail[0][2], 'nie oznacza awarii'), 'Test mail is unmistakably marked');
    assertAlert(scandir($root) === $beforeFiles, 'Test delivery creates no incident/telemetry files');
    $summary = mazurSendIntegrationTest($recipients, static fn($to, $subject, $body): bool => $to === 'one@example.invalid');
    assertAlert($summary === ['sent' => 1, 'failed' => 1], 'Test delivery reports partial failure');
    $summary = mazurSendIntegrationTest($recipients, static function(): bool { throw new RuntimeException('password=HIDDEN'); });
    assertAlert($summary === ['sent' => 0, 'failed' => 2], 'Test transport exception is counted without exposure');
    assertAlert(mazurIntegrationCliMode([]) === 'monitor', 'Default CLI mode is monitoring');
    assertAlert(mazurIntegrationCliMode(['--test-mail']) === 'test', 'Explicit test CLI mode is recognized');
    foreach ([['--unknown'], ['--test-mail', 'extra'], ['']] as $arguments) {
        $rejected = false;
        try { mazurIntegrationCliMode($arguments); } catch (InvalidArgumentException $error) { $rejected = true; }
        assertAlert($rejected, 'Unexpected CLI arguments must be rejected');
    }
    if (PHP_SAPI === 'cli') {
        // Native CI checks the actual OS lock; WebAssembly uses the injected boundary above.
        $lockedPath = $root . '/native.json';
        $held = fopen($lockedPath . '.lock', 'c');
        if ($held === false || !flock($held, LOCK_EX | LOCK_NB)) throw new RuntimeException('Cannot create native lock fixture');
        try {
            $summary = mazurNotifyIntegrationStatus(['feeds' => ['mls' => $feed('error')]], $lockedPath, $recipients, $send, $now);
            assertAlert($summary['busy'] && !is_file($lockedPath), 'Actual OS lock prevents concurrent duplicate sends');
        } finally { fclose($held); }
        $summary = mazurNotifyIntegrationStatus(['feeds' => ['mls' => $feed('error')]], $lockedPath, $recipients, $send, $now);
        assertAlert($summary['sent'] === 2, 'Actual OS lock is released after prior monitor exits');
    }
    echo "Integration state thresholds, incident/recipient deduplication, cooldown, monitor failures and panel parity: $checks checks passed\n";
} finally { removeAlertFixtures($root); }
