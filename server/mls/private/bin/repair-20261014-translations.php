<?php
declare(strict_types=1);

// One-off, private CLI repair. Never calls OpenAI or resets unrelated failures.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
require_once __DIR__ . '/translate-offers.php';
require_once __DIR__ . '/import-vixcrm.php';

function repair24Translations(): array
{
    return [
        164989 => ['mls', '351456', '-całodobowy monitoring i portiernia,', '- 24h monitoring and concierge service,'],
        153415 => ['vixcrm', 'O-237', '- Całodobowy dostęp do biura', '- 24h office access'],
        147751 => ['vixcrm', 'O-14', '- Całodobowy dostęp do biura', '- 24h office access'],
        127987 => ['mls', '382500', '- całodobowa ochrona,', '- 24h security,'],
        70138 => ['mls', '375396', '+całodobowa portiernia', '+ 24h concierge service'],
        65872 => ['mls', '374541', '- Całodobowa ochrona i portiernia, domofony', '- 24h security and concierge service, intercoms'],
        57502 => ['mls', '372272', 'Bezpieczeństwo i komfort - całodobowy monitoring, ochrona, wideodomofon oraz drzwi antywłamaniowe.', 'Security and comfort - 24h monitoring, security, video intercom and security doors.'],
        8035 => ['mls', '344502', '- całodobowa ochrona i monitoring,', '- 24h security and monitoring,'],
    ];
}

function repair24Run(PDO $db, bool $apply, string $backupPath): array
{
    $plan = []; $snapshot = [];
    $db->beginTransaction();
    try {
        // Lock in the same offer -> source order as the importer/reconciler.
        foreach (repair24Translations() as $id => [$source, $sourceId, $polish, $english]) {
            foreach (['mls_offers', 'offer_translation_sources'] as $table) {
                $lock = $db->prepare("SELECT * FROM $table WHERE source=? AND source_id=? FOR UPDATE");
                $lock->execute([$source, $sourceId]);
                $row = $lock->fetch(PDO::FETCH_ASSOC);
                $lock->closeCursor();
                if (!$row) throw new RuntimeException("Missing current source for $id");
                $snapshot[$table][$source . ':' . $sourceId] = $row;
            }
            $select = $db->prepare('SELECT * FROM offer_translation_jobs WHERE id=? FOR UPDATE');
            $select->execute([$id]);
            $job = $select->fetch(PDO::FETCH_ASSOC);
            $select->closeCursor();
            if (!$job || $job['source'] !== $source || $job['source_id'] !== $sourceId || $job['language'] !== 'en'
                || !in_array($job['state'], ['failed', 'done'], true)) throw new RuntimeException("Unexpected job $id");
            $segment = trCurrentSegment($db, $job);
            if (!$segment || $segment['pl_text'] !== $polish || $segment['runs'] !== [['text' => $polish]]) throw new RuntimeException("Source or formatting changed for $id");
            [, $tokens] = trProtectRuns($segment['runs']);
            $runs = trValidateRuns([['text' => $english, 'strong' => false, 'em' => false]], $tokens, strlen($polish), $segment['runs']);
            $args = [$source, $sourceId, $job['segment_id'], 'en', $job['pl_hash']];
            $unit = $db->prepare('SELECT * FROM offer_translation_units WHERE source=? AND source_id=? AND segment_id=? AND language=? AND pl_hash=?');
            $unit->execute($args);
            $existing = $unit->fetch(PDO::FETCH_ASSOC);
            $unit->closeCursor();
            if ($existing && json_decode($existing['translated_runs_json'], true, 512, JSON_THROW_ON_ERROR) !== $runs) throw new RuntimeException("Different translation already exists for $id");
            $snapshot['jobs'][$id] = $job;
            $snapshot['units'][$id] = $existing ?: null;
            $plan[$id] = [$job, $segment, $runs];
        }
        $offerQuery = $db->query("SELECT * FROM mls_offers WHERE source='vixcrm' AND source_id='O-5' AND publishable=1 FOR UPDATE");
        $offer = $offerQuery->fetch(PDO::FETCH_ASSOC);
        $offerQuery->closeCursor();
        if (!$offer) throw new RuntimeException('O-5 missing or no longer visible');
        $fields = json_decode($offer['fields_json'], true, 512, JSON_THROW_ON_ERROR);
        $description = (string)($fields['descriptionWebsite'] ?? '');
        $corrected = vixCorrectDescription('O-5', $description);
        if ($corrected === $description && !str_contains($description, 'otoczone rozległym prywatnym ogrodem')) throw new RuntimeException('O-5 source no longer matches correction');
        $snapshot['O-5'] = $offer;
        if ($apply) {
            // Save an immutable private backup before the first database write.
            if (!is_file($backupPath) && file_put_contents($backupPath, json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) throw new RuntimeException('Cannot save repair backup');
            foreach ($plan as [$job, $segment, $runs]) {
                $db->prepare('INSERT IGNORE INTO offer_translation_units (source,source_id,segment_id,language,pl_hash,source_pl_text,translated_runs_json,model_name) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$job['source'], $job['source_id'], $job['segment_id'], 'en', $job['pl_hash'], $segment['pl_text'], json_encode($runs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'manual:20261014-24h']);
                $db->prepare("UPDATE offer_translation_jobs SET state='done',last_error='' WHERE id=?")->execute([$job['id']]);
                mazurTrRefreshPublic($db, $job['source'], $job['source_id'], 'en');
            }
            if ($corrected !== $description) {
                $fields['descriptionWebsite'] = $corrected;
                if (is_string($fields['description'] ?? null)) $fields['description'] = vixCorrectDescription('O-5', $fields['description']);
                $db->prepare("UPDATE mls_offers SET fields_json=? WHERE source='vixcrm' AND source_id='O-5'")->execute([json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
                // The public API rejects this old translation until reconciliation.
                $db->exec("UPDATE offer_translation_sources SET synced_batch_sha256='' WHERE source='vixcrm' AND source_id='O-5'");
            }
            $db->commit();
        } else {
            $db->rollBack();
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
    if ($apply) {
        // Reuse every unchanged unit; only the corrected fragment needs API work.
        mazurTrReconcile($db, 'vixcrm', 'O-5');
        foreach ([159871, 159872, 159873] as $id) {
            $query = $db->prepare("SELECT * FROM offer_translation_jobs WHERE id=? AND source='vixcrm' AND source_id='O-5' AND state='failed'");
            $query->execute([$id]);
            $old = $query->fetch(PDO::FETCH_ASSOC);
            $query->closeCursor();
            if ($old && trCurrentSegment($db, $old) === null) $db->prepare("UPDATE offer_translation_jobs SET state='done',last_error='stale' WHERE id=? AND state='failed'")->execute([$id]);
        }
    }
    return ['mode' => $apply ? 'applied' : 'check', 'manual_24h_jobs' => array_keys($plan), 'O-5_typo' => $corrected !== $description ? 'corrected' : 'already corrected'];
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;
try {
    if ($argc !== 2 || !in_array($argv[1], ['--check', '--apply'], true)) throw new InvalidArgumentException('Use --check or --apply');
    $root = dirname(__DIR__);
    $receipt = $root . '/logs/repair-20261014-translations-applied.json';
    if ($argv[1] === '--apply' && is_file($receipt)) { echo "Repair already completed.\n"; exit(0); }
    $config = require $root . '/config.php';
    require_once (string)($config['translation_formatter_path'] ?? dirname($root) . '/public_html/api/description-formatter.php');
    $db = trDb($config);
    if ((int)$db->query("SELECT GET_LOCK('mazur_offer_translations',10)")->fetchColumn() !== 1) throw new RuntimeException('Translator busy; retry later');
    $importLock = fopen(sys_get_temp_dir() . '/mazur-vixcrm-import.lock', 'c');
    if ($importLock === false || !flock($importLock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Importer busy; retry later');
    try {
        $result = repair24Run($db, $argv[1] === '--apply', $root . '/logs/repair-20261014-translations-before.json');
        $json = json_encode($result + ['at' => gmdate('c')], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if ($argv[1] === '--apply' && file_put_contents($receipt, $json, LOCK_EX) === false) throw new RuntimeException('Cannot save receipt');
        echo $json . "\n";
    } finally {
        flock($importLock, LOCK_UN); fclose($importLock);
        $db->query("SELECT RELEASE_LOCK('mazur_offer_translations')")->closeCursor();
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n"); exit(1);
}
