CREATE TABLE IF NOT EXISTS offer_parameter_sources (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  synced_batch_sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offer_parameter_translations (
  phrase_hash CHAR(64) CHARACTER SET ascii NOT NULL,
  language CHAR(2) CHARACTER SET ascii NOT NULL,
  kind VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_text TEXT NOT NULL,
  translated_text TEXT NOT NULL,
  state ENUM('pending','processing','retry','failed','done') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_error VARCHAR(255) NOT NULL DEFAULT '',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (phrase_hash, language),
  INDEX idx_parameter_pending (state, next_attempt_at)
) ENGINE=InnoDB;
