<?php
declare(strict_types=1);

function mazurIntegrationCliMode(array $arguments): string
{
    if ($arguments === []) return 'monitor';
    if ($arguments === ['--test-mail']) return 'test';
    throw new InvalidArgumentException('Unknown integration monitor arguments');
}

function mazurIntegrationRecipients(array $config, ?string $recipientFile = null): array
{
    $values = $config['integration_notification_emails'] ?? [];
    if ($recipientFile !== null && is_file($recipientFile)) {
        if (filesize($recipientFile) > 4096) throw new RuntimeException('Invalid integration notification recipients');
        $override = require $recipientFile;
        if (!is_array($override) || !array_key_exists('emails', $override)) throw new RuntimeException('Invalid integration notification recipients');
        $values = $override['emails'];
    }
    if (!is_array($values)) throw new RuntimeException('Invalid integration notification recipients');
    $recipients = [];
    foreach ($values as $value) {
        if (!is_string($value) || strlen($value) > 254 || preg_match('/[\r\n]/', $value)
            || !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid integration notification recipients');
        $address = strtolower($value);
        $recipients[$address] = $address;
    }
    return array_values($recipients);
}

function mazurIntegrationAlertMessage(string $source, array $feed): array
{
    $name = ['mls' => 'MLS', 'esticrm' => 'EstiCRM', 'vixcrm' => 'VixCRM', 'monitor' => 'monitor integracji'][$source];
    $state = (string)$feed['state'];
    $reason = (string)($feed['state_reason'] ?? '');
    $labels = ['error' => 'Wymaga uwagi', 'stale' => 'Sprawdź harmonogram', 'unknown' => 'Brak telemetrii', 'quiet' => 'Brak nowych paczek'];
    $description = match ($state) {
        'stale' => match ($reason) {
            'incoming' => 'Paczka oczekuje ponad 2 godziny i 15 minut na import.',
            'processing' => 'Paczka pozostaje w trakcie przetwarzania ponad 90 minut.',
            default => 'Brak potwierdzenia uruchomienia importera od ponad 2 godzin i 15 minut albo import trwa ponad 90 minut.',
        },
        'quiet' => 'Importer działa, ale nie zaimportował nowej paczki MLS od ponad trzech godzin.',
        'unknown' => 'Brakuje poprawnych danych o uruchomieniu importera.',
        default => $source === 'monitor' ? 'Nie udało się sprawdzić statusu integracji. Sprawdź dostęp do bazy danych i plików monitorowania.'
            : 'Importer zgłosił błąd albo w katalogu błędów pozostały paczki wymagające sprawdzenia. Szczegółowe raporty są dostępne na serwerze.',
    };
    $lines = ['Źródło: ' . $name, 'Status: ' . ($labels[$state] ?? 'Wymaga uwagi'), $description, ''];
    if ($source !== 'monitor') {
        $lines[] = 'Paczki oczekujące / w trakcie / z błędem: ' . (int)($feed['incoming'] ?? 0) . ' / '
            . (int)($feed['processing'] ?? 0) . ' / ' . (int)($feed['errors'] ?? 0);
        $zone = new DateTimeZone('Europe/Warsaw');
        foreach (['Ostatnie uruchomienie' => $feed['last_run_at'] ?? null,
            'Ostatni import' => $feed['batches'][0]['imported_at'] ?? null] as $label => $at) {
            $lines[] = $label . ': ' . ($at ? (new DateTimeImmutable('@' . (int)$at))->setTimezone($zone)->format('d.m.Y H:i:s T') : 'brak danych');
        }
    }
    $lines[] = '';
    $lines[] = 'Panel: https://api.mazurestate.pl/api/featured-admin.php';
    // Raw exception/error text may contain connection details. It never enters emails.
    return ['MazurEstate - status integracji - ' . $name, implode("\n", $lines)];
}

function mazurSaveIntegrationAlerts(string $path, array $state): void
{
    $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $temp = tempnam(dirname($path), '.integration-alert-');
    if ($temp === false) throw new RuntimeException('Cannot create integration alert state');
    try {
        chmod($temp, 0600);
        if (file_put_contents($temp, $json, LOCK_EX) !== strlen($json) || !rename($temp, $path)) {
            throw new RuntimeException('Cannot save integration alert state');
        }
    } finally { if (is_file($temp)) unlink($temp); }
}

/**
 * One notification per state/reason incident and recipient. Running is not recovery.
 * The lock is separate from the import lock, so this cannot delay package imports.
 * Injected mail and lock boundaries let tests run without delivery or an OS flock.
 */
function mazurNotifyIntegrationStatus(array $snapshot, string $path, array $recipients, callable $send,
    ?int $now = null, ?callable $acquire = null): array
{
    $now ??= time();
    $summary = ['sent' => 0, 'failed' => 0, 'busy' => false];
    if ($recipients === []) return $summary;
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create integration alert storage');
    }
    $lock = fopen($path . '.lock', 'c');
    if ($lock === false) throw new RuntimeException('Cannot open integration alert lock');
    $acquire ??= static function($handle): bool {
        $wouldBlock = 0;
        $acquired = flock($handle, LOCK_EX | LOCK_NB, $wouldBlock);
        if (!$acquired && !$wouldBlock) throw new RuntimeException('Cannot acquire integration alert lock');
        return $acquired;
    };
    try {
        if (!$acquire($lock)) { $summary['busy'] = true; return $summary; }
        $state = ['version' => 1, 'feeds' => []];
        if (is_file($path)) {
            if (filesize($path) > 128 * 1024) throw new RuntimeException('Invalid integration alert state');
            $state = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($state) || ($state['version'] ?? null) !== 1 || !is_array($state['feeds'] ?? null)) {
                throw new RuntimeException('Invalid integration alert state');
            }
        }
        foreach (['mls', 'esticrm', 'vixcrm', 'monitor'] as $source) {
            $feed = $snapshot['feeds'][$source] ?? null;
            if (!is_array($feed)) continue;
            $current = (string)($feed['state'] ?? 'unknown');
            if (($feed['enabled'] ?? false) !== true || $current === 'ok' || $current === 'disabled') {
                unset($state['feeds'][$source]);
                continue;
            }
            $alertable = $source === 'vixcrm' ? $current === 'error' : in_array($current, ['error', 'stale', 'unknown', 'quiet'], true);
            if (!$alertable) continue;
            $key = $current . ':' . (string)($feed['state_reason'] ?? '');
            $incident = $state['feeds'][$source] ?? [];
            if (($incident['key'] ?? '') !== $key) $incident = ['key' => $key, 'first_seen_at' => $now, 'recipients' => []];
            $state['feeds'][$source] = $incident;
            [$subject, $body] = mazurIntegrationAlertMessage($source, $feed);
            foreach ($recipients as $recipient) {
                $recipientKey = hash('sha256', $recipient);
                $delivery = $state['feeds'][$source]['recipients'][$recipientKey] ?? [];
                if (!empty($delivery['sent_at']) || (isset($delivery['attempted_at']) && $now - $delivery['attempted_at'] < 300)) continue;
                $delivery['attempted_at'] = $now;
                $state['feeds'][$source]['recipients'][$recipientKey] = $delivery;
                // Persist attempts before sending, then each successful recipient separately.
                mazurSaveIntegrationAlerts($path, $state);
                try { $sent = $send($recipient, $subject, $body) === true; }
                catch (Throwable $error) { $sent = false; }
                if ($sent) {
                    $state['feeds'][$source]['recipients'][$recipientKey]['sent_at'] = $now;
                    $summary['sent']++;
                } else { $summary['failed']++; }
                mazurSaveIntegrationAlerts($path, $state);
            }
        }
        mazurSaveIntegrationAlerts($path, $state);
        return $summary;
    } finally {
        // Closing the handle releases the lock even when storage or delivery fails.
        fclose($lock);
    }
}

function mazurIntegrationMonitorFailure(): array
{
    return ['feeds' => ['monitor' => ['enabled' => true, 'state' => 'error', 'state_reason' => 'check']]];
}

/** Explicit delivery test only: no database, telemetry or incident state access. */
function mazurSendIntegrationTest(array $recipients, callable $send): array
{
    $summary = ['sent' => 0, 'failed' => 0];
    $subject = 'MazurEstate - TEST powiadomień integracji';
    $body = "To jednorazowy TEST powiadomień mailowych MazurEstate.\n"
        . "Ta wiadomość nie oznacza awarii integracji.\n"
        . "Test nie uruchamia importu i nie zmienia statusów ani ofert.\n\n"
        . "Panel: https://api.mazurestate.pl/api/featured-admin.php\n";
    foreach ($recipients as $recipient) {
        try { $sent = $send($recipient, $subject, $body) === true; }
        catch (Throwable $error) { $sent = false; }
        if ($sent) $summary['sent']++; else $summary['failed']++;
    }
    return $summary;
}
