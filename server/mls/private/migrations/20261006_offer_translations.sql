-- Apply once to the existing MLS database before enabling the translator cron.
CREATE TABLE IF NOT EXISTS offer_translation_sources (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  signature CHAR(64) CHARACTER SET ascii NOT NULL,
  synced_batch_sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
  title_segment_id VARCHAR(32) CHARACTER SET ascii NOT NULL,
  segments_json LONGTEXT NOT NULL,
  document_json LONGTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offer_translation_units (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  segment_id VARCHAR(32) CHARACTER SET ascii NOT NULL,
  language CHAR(2) CHARACTER SET ascii NOT NULL,
  pl_hash CHAR(64) CHARACTER SET ascii NOT NULL,
  source_pl_text LONGTEXT NOT NULL,
  translated_runs_json LONGTEXT NOT NULL,
  model_name VARCHAR(80) CHARACTER SET ascii NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_id, segment_id, language, pl_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offer_translation_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  segment_id VARCHAR(32) CHARACTER SET ascii NOT NULL,
  language CHAR(2) CHARACTER SET ascii NOT NULL,
  pl_hash CHAR(64) CHARACTER SET ascii NOT NULL,
  state ENUM('pending','processing','retry','failed','done') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_error VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_translation_job (source, source_id, segment_id, language, pl_hash),
  INDEX idx_translation_pending (state, next_attempt_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offer_translation_public (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  language CHAR(2) CHARACTER SET ascii NOT NULL,
  signature CHAR(64) CHARACTER SET ascii NOT NULL,
  title TEXT NOT NULL,
  description_document_json LONGTEXT NOT NULL,
  plain_description LONGTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_id, language)
) ENGINE=InnoDB;
