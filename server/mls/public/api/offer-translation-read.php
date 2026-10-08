<?php
declare(strict_types=1);

// Public read-only access. A missing migration safely leaves offers in Polish.
function mazurTranslationLanguage(mixed $value): string
{
    return in_array($value, ['en', 'uk', 'ru'], true) ? $value : 'pl';
}

function mazurTranslationForOffer(PDO $db, string $source, string $sourceId, string $language): ?array
{
    if ($language === 'pl') return null;
    try {
        $query = $db->prepare('SELECT p.title,p.plain_description,p.description_document_json FROM offer_translation_public p JOIN offer_translation_sources s ON s.source=p.source AND s.source_id=p.source_id AND s.signature=p.signature JOIN mls_offers o ON o.source=s.source AND o.source_id=s.source_id AND o.publishable=1 AND o.batch_sha256=s.synced_batch_sha256 WHERE p.source=? AND p.source_id=? AND p.language=? LIMIT 1');
        $query->execute([$source, $sourceId, $language]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        $query->closeCursor();
        if (!$row) return null;
        $document = json_decode($row['description_document_json'], true, 512, JSON_THROW_ON_ERROR);
        if (($document['language'] ?? '') !== $language || trim($row['title']) === '') return null;
        return ['title' => $row['title'], 'description' => $row['plain_description'], 'descriptionDocument' => $document];
    } catch (Throwable $error) {
        error_log('Mazur offer translation read unavailable: ' . get_class($error));
        return null;
    }
}

function mazurTranslationTitles(PDO $db, string $language): array
{
    if ($language === 'pl') return [];
    try {
        $query = $db->prepare('SELECT p.source,p.source_id,p.title FROM offer_translation_public p JOIN offer_translation_sources s ON s.source=p.source AND s.source_id=p.source_id AND s.signature=p.signature JOIN mls_offers o ON o.source=s.source AND o.source_id=s.source_id AND o.publishable=1 AND o.batch_sha256=s.synced_batch_sha256 WHERE p.language=?');
        $query->execute([$language]);
        $titles = [];
        foreach ($query as $row) $titles[$row['source'] . ':' . $row['source_id']] = $row['title'];
        $query->closeCursor();
        return $titles;
    } catch (Throwable $error) {
        error_log('Mazur offer translation titles unavailable: ' . get_class($error));
        return [];
    }
}
