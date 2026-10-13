<?php
declare(strict_types=1);

// Read-only monitoring, called only after the admin session is authorized.
// Do not load the worker: viewing the panel must never run paid translations.
function mazurTranslationQueueCounts(): array
{
    return array_fill_keys(['pending', 'processing', 'retry', 'failed', 'done'], 0);
}

function mazurTranslationQueueSummary(PDO $db, string $queue): array
{
    $table = $queue === 'offers' ? 'offer_translation_jobs' : 'offer_parameter_translations';
    $summary = [
        'available' => false,
        'counts' => mazurTranslationQueueCounts(),
        'languages' => array_fill_keys(['en', 'uk', 'ru'], mazurTranslationQueueCounts()),
        'last_activity_at' => null,
        'last_completed_at' => null,
        'stale_done' => 0,
    ];
    try {
        $rows = $db->query("SELECT language, state, COUNT(*) AS jobs,
            UNIX_TIMESTAMP(MAX(updated_at)) AS last_activity_at,
            UNIX_TIMESTAMP(MAX(CASE WHEN state='done' AND last_error<>'stale' THEN updated_at END)) AS last_completed_at,
            SUM(state='done' AND last_error='stale') AS stale_done
            FROM $table GROUP BY language, state")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $language = (string)$row['language'];
            $state = (string)$row['state'];
            if (!isset($summary['languages'][$language]) || !array_key_exists($state, $summary['counts'])) continue;
            $count = (int)$row['jobs'];
            $summary['counts'][$state] += $count;
            $summary['languages'][$language][$state] += $count;
            foreach (['last_activity_at', 'last_completed_at'] as $field) {
                $value = (int)($row[$field] ?? 0);
                if ($value > (int)($summary[$field] ?? 0)) $summary[$field] = $value;
            }
            $summary['stale_done'] += (int)$row['stale_done'];
        }
        $summary['available'] = true;
    } catch (Throwable $error) {
        error_log('Mazur translation queue summary unavailable: ' . $queue . ' ' . get_class($error));
    }
    return $summary;
}

function mazurTranslationQueueErrorMessage(string $error): string
{
    if (preg_match('/\bOpenAI HTTP (\d{3})\b/', $error, $match)) {
        return match ((int)$match[1]) {
            400 => 'Usługa tłumaczeń odrzuciła zlecenie.',
            401, 403 => 'Usługa tłumaczeń odrzuciła dostęp. Sprawdź konfigurację klucza.',
            402 => 'Sprawdź rozliczenia konta API.',
            429 => 'Limit API lub niedostępne saldo. Zadanie wymaga ponowienia.',
            default => 'Usługa tłumaczeń zwróciła błąd. Zadanie wymaga ponowienia.',
        };
    }
    if ($error === 'interrupted') return 'Poprzednie przetwarzanie zostało przerwane.';
    if (stripos($error, 'timed out') !== false || stripos($error, 'timeout') !== false) return 'Przekroczono czas oczekiwania na tłumaczenie.';
    if (str_contains($error, 'OpenAI transport error')) return 'Nie udało się połączyć z usługą tłumaczeń.';
    if (str_contains($error, 'OpenAI refused')) return 'Usługa nie zwróciła tłumaczenia tego fragmentu.';
    if (preg_match('/Protected value|Unexpected number|translation|translated|runs|OpenAI response|OpenAI returned/i', $error)) return 'Nie udało się uzyskać poprawnego tłumaczenia.';
    return 'Zadanie wymaga sprawdzenia przed ponowieniem.';
}

function mazurTranslationQueueErrors(PDO $db, string $queue): array
{
    try {
        if ($queue === 'offers') {
            $rows = $db->query("SELECT j.source,j.source_id,j.language,j.state,j.attempts,j.last_error,
                UNIX_TIMESTAMP(j.updated_at) AS updated_at,o.fields_json
                FROM offer_translation_jobs j LEFT JOIN mls_offers o ON o.source=j.source AND o.source_id=j.source_id
                WHERE j.state IN ('failed','retry') ORDER BY j.updated_at DESC,j.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $db->query("SELECT language,state,attempts,last_error,source_text,
                UNIX_TIMESTAMP(updated_at) AS updated_at FROM offer_parameter_translations
                WHERE state IN ('failed','retry') ORDER BY updated_at DESC,phrase_hash,language LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        }
        $errors = [];
        foreach ($rows as $row) {
            if (!in_array($row['language'], ['en', 'uk', 'ru'], true)) continue;
            $item = [
                'queue' => $queue,
                'state' => (string)$row['state'],
                'language' => (string)$row['language'],
                'attempts' => (int)$row['attempts'],
                'updated_at' => (int)$row['updated_at'],
                'message' => mazurTranslationQueueErrorMessage((string)$row['last_error']),
            ];
            if ($queue === 'offers') {
                $source = (string)$row['source'];
                $id = (string)$row['source_id'];
                if (in_array($source, ['mls', 'esticrm', 'vixcrm'], true) && preg_match('/\A[0-9]{1,40}\z/', $id)) {
                    $item['source'] = $source;
                    $item['offer_id'] = ($source === 'esticrm' ? 'esti-' : ($source === 'vixcrm' ? 'vix-' : '')) . $id;
                }
                $fields = json_decode((string)($row['fields_json'] ?? ''), true);
                if (is_array($fields)) {
                    $item['offer_number'] = mb_substr(trim((string)(($fields['numberExport'] ?? '') ?: ($fields['number'] ?? ''))), 0, 80);
                }
            } else {
                $item['phrase'] = mb_substr((string)$row['source_text'], 0, 300);
            }
            $errors[] = $item;
        }
        return [true, $errors];
    } catch (Throwable $error) {
        error_log('Mazur translation queue errors unavailable: ' . $queue . ' ' . get_class($error));
        return [false, []];
    }
}

function mazurTranslationPublicationSummary(PDO $db): array
{
    $summary = ['available' => false, 'active_offers' => 0, 'languages' => array_fill_keys(['en', 'uk', 'ru'], 0)];
    try {
        $summary['active_offers'] = (int)$db->query('SELECT COUNT(*) FROM mls_offers WHERE publishable=1')->fetchColumn();
        // Match the public API's current source signature and imported batch.
        $rows = $db->query("SELECT p.language,COUNT(*) AS ready
            FROM offer_translation_public p
            JOIN offer_translation_sources s ON s.source=p.source AND s.source_id=p.source_id AND s.signature=p.signature
            JOIN mls_offers o ON o.source=s.source AND o.source_id=s.source_id AND o.publishable=1 AND o.batch_sha256=s.synced_batch_sha256
            WHERE p.language IN ('en','uk','ru') AND TRIM(p.title)<>''
            AND CASE WHEN JSON_VALID(p.description_document_json)
                THEN JSON_UNQUOTE(JSON_EXTRACT(p.description_document_json,'$.language')) ELSE NULL END=p.language
            GROUP BY p.language")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) $summary['languages'][$row['language']] = (int)$row['ready'];
        $summary['available'] = true;
    } catch (Throwable $error) {
        error_log('Mazur translation publication summary unavailable: ' . get_class($error));
    }
    return $summary;
}

function mazurTranslationQueueStatus(PDO $db, array $config, ?int $now = null): array
{
    $queues = [];
    $errors = [];
    $errorsAvailable = true;
    foreach (['offers', 'parameters'] as $queue) {
        $queues[$queue] = mazurTranslationQueueSummary($db, $queue);
        if ($queues[$queue]['available']) {
            [$available, $items] = mazurTranslationQueueErrors($db, $queue);
            $errorsAvailable = $errorsAvailable && $available;
            $errors = array_merge($errors, $items);
        } else {
            $errorsAvailable = false;
        }
    }
    usort($errors, static fn(array $a, array $b): int => $b['updated_at'] <=> $a['updated_at']);
    return [
        'checked_at' => $now ?? time(),
        'max_jobs_per_run' => max(1, min(30, (int)($config['translation_max_jobs_per_run'] ?? 30))),
        'queues' => $queues,
        'publication' => mazurTranslationPublicationSummary($db),
        'errors' => array_slice($errors, 0, 8),
        'errors_available' => $errorsAvailable,
    ];
}
