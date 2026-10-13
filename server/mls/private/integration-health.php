<?php
declare(strict_types=1);

/** Shared by the authenticated panel and the independent CLI monitor. */
function mazurIntegrationState(string $source, bool $enabled, array $health, array $counts, array $oldest, array $batches, int $now): array
{
    $lastRun = (int)($health['last_run_at'] ?? 0);
    $result = (string)($health['result'] ?? '');
    if (!$enabled) return ['disabled', ''];
    if (($counts['errors'] ?? 0) > 0 || $result === 'error') return ['error', ''];
    if ($lastRun === 0 || !in_array($result, ['success', 'running'], true)) return ['unknown', ''];
    if ($now - $lastRun > 8100 || ($result === 'running' && $now - $lastRun > 5400)) return ['stale', 'run'];
    if (($oldest['incoming'] ?? 0) && $now - $oldest['incoming'] > 8100) return ['stale', 'incoming'];
    if (($oldest['processing'] ?? 0) && $now - $oldest['processing'] > 5400) return ['stale', 'processing'];
    if ($result === 'running') return ['running', ''];
    if ($source === 'mls' && (!$batches || $now - (int)$batches[0]['imported_at'] > 10800)) return ['quiet', ''];
    return ['ok', ''];
}

function integrationStatus(PDO $db, array $config, ?string $root = null, ?int $now = null): array
{
    $root ??= __DIR__;
    $now ??= time();
    $sourceColumn = $db->query("SHOW COLUMNS FROM mls_batches LIKE 'source'")->fetch() !== false;
    $offerSourceColumn = $db->query("SHOW COLUMNS FROM mls_offers LIKE 'source'")->fetch() !== false;
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
        [$state, $reason] = mazurIntegrationState($source, $enabled, $health ?? [], $counts, $oldest, $batches, $now);
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
    return ['checked_at' => $now, 'retention_days' => max(1, (int)($config['archive_retention_days'] ?? 7)), 'feeds' => $feeds];
}
