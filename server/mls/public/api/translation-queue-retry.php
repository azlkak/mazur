<?php
declare(strict_types=1);

// Explicit admin action: requeue failed work without invoking OpenAI here.
function mazurRetryFailedTranslations(PDO $db, string $queue, int $expectedFailed): array
{
    $tables = ['offers' => 'offer_translation_jobs', 'parameters' => 'offer_parameter_translations'];
    if (!isset($tables[$queue]) || $expectedFailed < 0) throw new InvalidArgumentException('Invalid retry request');
    $table = $tables[$queue];
    $lock = $db->query("SELECT GET_LOCK('mazur_offer_translations',0)");
    $acquired = (int)$lock->fetchColumn() === 1;
    $lock->closeCursor();
    if (!$acquired) return ['ok' => false, 'error' => 'translation_busy'];
    try {
        $db->beginTransaction();
        $where = "state='failed' AND language IN ('en','uk','ru')";
        $failed = (int)$db->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn();
        if ($failed !== $expectedFailed) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'queue_changed', 'failed' => $failed];
        }
        $requeued = 0;
        if ($failed > 0) {
            $requeued = $db->exec("UPDATE $table SET state='pending',attempts=0,next_attempt_at=UTC_TIMESTAMP(),last_error=''
                WHERE $where LIMIT $expectedFailed");
            if ($requeued !== $expectedFailed) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'queue_changed'];
            }
        }
        $db->commit();
        if ($requeued > 0) error_log('Mazur translation admin retry: ' . $queue . ' requeued=' . $requeued);
        return ['ok' => true, 'queue' => $queue, 'requeued' => $requeued];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    } finally {
        $db->query("SELECT RELEASE_LOCK('mazur_offer_translations')")->closeCursor();
    }
}
