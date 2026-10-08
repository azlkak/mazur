<?php
declare(strict_types=1);

// Shared, database-free source preparation plus private translation persistence.
// No network calls are made by this file. Install outside public_html.

const MAZUR_TRANSLATION_LANGUAGES = ['en', 'uk', 'ru'];

function mazurTrFirstText(array $fields, string ...$keys): string
{
    foreach ($keys as $key) {
        $value = trim((string)($fields[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
}

function mazurTrPlainDescription(string $value): string
{
    $value = preg_replace('~<(?:br|hr)\b[^>]*>|</(?:p|div|h[1-6]|li|ul|ol|section|article|blockquote|tr|td|th)\s*>~i', "\n", $value) ?? $value;
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(["\r\n", "\r", "\u{00A0}", "\u{202F}"], ["\n", "\n", ' ', ' '], $value);
    $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value);
}

function mazurTrSource(array $fields): array
{
    $type = mazurTrFirstText($fields, 'typeName') ?: 'Nieruchomość';
    $city = mazurTrFirstText($fields, 'locationExportCityName', 'locationCityName');
    $title = mazurTrFirstText($fields, 'portalTitle') ?: $type . ($city !== '' ? ' — ' . $city : '');
    $raw = mazurTrFirstText($fields, 'descriptionWebsite', 'description');
    $document = function_exists('mazurDescriptionDocument') ? mazurDescriptionDocument($raw) : null;
    if (!is_array($document)) {
        $blocks = [];
        foreach (preg_split('/\n+/u', mazurTrPlainDescription($raw)) ?: [] as $line) {
            if (trim($line) !== '') $blocks[] = ['type' => 'paragraph', 'runs' => [['text' => trim($line)]]];
        }
        $document = ['schemaVersion' => 1, 'language' => 'pl', 'format' => 'text', 'blocks' => $blocks];
    }
    return [$title, $document];
}

function mazurTrHash(string $kind, array $runs): string
{
    return hash('sha256', json_encode([$kind, $runs], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}

function mazurTrCandidateDocument(string $title, array $document): array
{
    $candidates = [[
        'kind' => 'title', 'pl_text' => $title, 'runs' => [['text' => $title]],
        'pl_hash' => mazurTrHash('title', [['text' => $title]]), 'position' => -1,
    ]];
    $position = 0;
    $walk = static function (array $blocks) use (&$walk, &$candidates, &$position): array {
        $out = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'list') {
                $copy = $block;
                $copy['items'] = [];
                foreach ($block['items'] ?? [] as $item) {
                    $copyItem = $item;
                    $copyItem['blocks'] = $walk($item['blocks'] ?? []);
                    $copy['items'][] = $copyItem;
                }
                $out[] = $copy;
                continue;
            }
            $kind = (string)($block['type'] ?? '');
            if (!in_array($kind, ['paragraph', 'heading'], true)) continue;
            $runs = $block['runs'] ?? [];
            $content = implode('', array_map(static fn(array $run): string => (string)($run['text'] ?? ''), $runs));
            if (trim($content) === '') continue;
            $candidates[] = [
                'kind' => $kind, 'pl_text' => $content, 'runs' => $runs,
                'pl_hash' => mazurTrHash($kind, $runs), 'position' => $position++,
            ];
            $out[] = ['type' => $kind, 'segmentId' => ''];
        }
        return $out;
    };
    $skeleton = [
        'schemaVersion' => 1, 'language' => 'pl', 'format' => $document['format'] ?? 'text',
        'blocks' => $walk($document['blocks'] ?? []),
    ];
    return [$candidates, $skeleton];
}

function mazurTrAssignSegments(array $candidates, array $previous): array
{
    $used = [];
    foreach ($candidates as &$candidate) {
        if ($candidate['kind'] === 'title') {
            $candidate['segment_id'] = 'title';
            continue;
        }
        foreach ($previous as $old) {
            $id = (string)($old['segment_id'] ?? '');
            if ($id !== '' && !isset($used[$id]) && $old['kind'] === $candidate['kind'] && $old['pl_hash'] === $candidate['pl_hash']) {
                $candidate['segment_id'] = $id;
                $used[$id] = true;
                break;
            }
        }
    }
    unset($candidate);
    foreach ($candidates as &$candidate) {
        if (isset($candidate['segment_id'])) continue;
        $best = null; $bestScore = 0.39;
        foreach ($previous as $old) {
            $id = (string)($old['segment_id'] ?? '');
            if ($id === '' || isset($used[$id]) || $old['kind'] !== $candidate['kind']) continue;
            if (abs((int)$old['position'] - (int)$candidate['position']) > 2) continue;
            $left = substr((string)$old['pl_text'], 0, 1200);
            $right = substr((string)$candidate['pl_text'], 0, 1200);
            similar_text($left, $right, $percent);
            $score = $percent / 100;
            if ($score > $bestScore) { $best = $id; $bestScore = $score; }
        }
        $candidate['segment_id'] = $best ?? bin2hex(random_bytes(12));
        if ($best !== null) $used[$best] = true;
    }
    unset($candidate);
    return $candidates;
}

function mazurTrFillSkeleton(array $skeleton, array $segments, ?array $translated = null): array
{
    $index = 1;
    $walk = static function (array $blocks) use (&$walk, &$index, $segments, $translated): array {
        foreach ($blocks as &$block) {
            if ($block['type'] === 'list') {
                foreach ($block['items'] as &$item) $item['blocks'] = $walk($item['blocks']);
                unset($item);
            } else {
                $segment = $segments[$index++] ?? null;
                if ($segment === null) throw new RuntimeException('Translation skeleton mismatch');
                if ($translated === null) $block['segmentId'] = $segment['segment_id'];
                else {
                    $key = $segment['segment_id'];
                    if (!isset($translated[$key])) throw new RuntimeException('Missing translated segment');
                    unset($block['segmentId']);
                    $block['runs'] = $translated[$key];
                }
            }
        }
        unset($block);
        return $blocks;
    };
    $skeleton['blocks'] = $walk($skeleton['blocks']);
    if ($index !== count($segments)) throw new RuntimeException('Translation segment count mismatch');
    return $skeleton;
}

function mazurTrSignature(array $segments, array $document): string
{
    return hash('sha256', json_encode([$segments, $document], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}

function mazurTrReconcile(PDO $db, string $source, string $sourceId): bool
{
    $db->beginTransaction();
    try {
        $offerQuery = $db->prepare('SELECT fields_json,batch_sha256 FROM mls_offers WHERE source=? AND source_id=? AND publishable=1 FOR UPDATE');
        $offerQuery->execute([$source, $sourceId]);
        $offerRow = $offerQuery->fetch(PDO::FETCH_ASSOC);
        $offerQuery->closeCursor();
        if (!$offerRow) { $db->commit(); return false; }
        $fields = json_decode($offerRow['fields_json'], true, 512, JSON_THROW_ON_ERROR);
        [$title, $document] = mazurTrSource($fields);
        [$candidates, $skeleton] = mazurTrCandidateDocument($title, $document);
        $select = $db->prepare('SELECT signature, segments_json FROM offer_translation_sources WHERE source=? AND source_id=? FOR UPDATE');
        $select->execute([$source, $sourceId]);
        $old = $select->fetch(PDO::FETCH_ASSOC);
        $select->closeCursor();
        $previous = $old ? json_decode($old['segments_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        $segments = mazurTrAssignSegments($candidates, $previous);
        $skeleton = mazurTrFillSkeleton($skeleton, $segments);
        $signature = mazurTrSignature($segments, $skeleton);
        if ($old && hash_equals($old['signature'], $signature)) {
            $db->prepare('UPDATE offer_translation_sources SET synced_batch_sha256=? WHERE source=? AND source_id=?')->execute([$offerRow['batch_sha256'], $source, $sourceId]);
            $db->commit(); return false;
        }
        $put = $db->prepare('INSERT INTO offer_translation_sources (source,source_id,signature,synced_batch_sha256,title_segment_id,segments_json,document_json) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE signature=VALUES(signature),synced_batch_sha256=VALUES(synced_batch_sha256),segments_json=VALUES(segments_json),document_json=VALUES(document_json)');
        $put->execute([$source, $sourceId, $signature, $offerRow['batch_sha256'], 'title', json_encode($segments, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), json_encode($skeleton, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        $db->prepare('DELETE FROM offer_translation_public WHERE source=? AND source_id=?')->execute([$source, $sourceId]);
        $has = $db->prepare('SELECT 1 FROM offer_translation_units WHERE source=? AND source_id=? AND segment_id=? AND language=? AND pl_hash=? LIMIT 1');
        $enqueue = $db->prepare("INSERT INTO offer_translation_jobs (source,source_id,segment_id,language,pl_hash) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE state='pending',attempts=0,next_attempt_at=UTC_TIMESTAMP(),last_error=''");
        foreach ($segments as $segment) foreach (MAZUR_TRANSLATION_LANGUAGES as $language) {
            $args = [$source, $sourceId, $segment['segment_id'], $language, $segment['pl_hash']];
            $has->execute($args);
            $exists = $has->fetchColumn() !== false;
            $has->closeCursor();
            if (!$exists) $enqueue->execute($args);
        }
        foreach (MAZUR_TRANSLATION_LANGUAGES as $language) mazurTrRefreshPublic($db, $source, $sourceId, $language, $segments, $skeleton, $signature);
        $db->commit();
        return true;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

function mazurTrRefreshPublic(PDO $db, string $source, string $sourceId, string $language, ?array $segments = null, ?array $skeleton = null, ?string $signature = null): bool
{
    if ($segments === null || $skeleton === null || $signature === null) {
        $stmt = $db->prepare('SELECT signature,segments_json,document_json FROM offer_translation_sources WHERE source=? AND source_id=?');
        $stmt->execute([$source, $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$row) return false;
        $segments = json_decode($row['segments_json'], true, 512, JSON_THROW_ON_ERROR);
        $skeleton = json_decode($row['document_json'], true, 512, JSON_THROW_ON_ERROR);
        $signature = $row['signature'];
    }
    $query = $db->prepare('SELECT segment_id,pl_hash,translated_runs_json FROM offer_translation_units WHERE source=? AND source_id=? AND language=?');
    $query->execute([$source, $sourceId, $language]);
    $available = [];
    foreach ($query as $row) $available[$row['segment_id']][$row['pl_hash']] = json_decode($row['translated_runs_json'], true, 512, JSON_THROW_ON_ERROR);
    $query->closeCursor();
    $translated = [];
    foreach ($segments as $segment) {
        $runs = $available[$segment['segment_id']][$segment['pl_hash']] ?? null;
        if (!is_array($runs) || $runs === []) {
            $db->prepare('DELETE FROM offer_translation_public WHERE source=? AND source_id=? AND language=?')->execute([$source, $sourceId, $language]);
            return false;
        }
        $translated[$segment['segment_id']] = $runs;
    }
    $title = implode('', array_column($translated['title'], 'text'));
    $localized = mazurTrFillSkeleton($skeleton, $segments, $translated);
    $localized['language'] = $language;
    $plain = implode("\n", array_map(static fn(array $segment): string => implode('', array_column($translated[$segment['segment_id']], 'text')), array_slice($segments, 1)));
    $put = $db->prepare('INSERT INTO offer_translation_public (source,source_id,language,signature,title,description_document_json,plain_description) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE signature=VALUES(signature),title=VALUES(title),description_document_json=VALUES(description_document_json),plain_description=VALUES(plain_description)');
    $put->execute([$source, $sourceId, $language, $signature, $title, json_encode($localized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $plain]);
    return true;
}
