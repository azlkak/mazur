CREATE TABLE IF NOT EXISTS mls_batches (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'mls',
  sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  export_type ENUM('incremental','full') NOT NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  offer_count INT UNSIGNED NOT NULL,
  PRIMARY KEY (source, sha256)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mls_offers (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'mls',
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  source_export_at DATETIME NOT NULL,
  batch_sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
  publishable BOOLEAN NOT NULL DEFAULT FALSE,
  location_city VARCHAR(191) NOT NULL DEFAULT '',
  location_district VARCHAR(191) NOT NULL DEFAULT '',
  fields_json LONGTEXT NOT NULL,
  images_json LONGTEXT NOT NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_id),
  INDEX (publishable),
  INDEX idx_search_location (publishable, location_city, location_district),
  INDEX (source_export_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portal_hidden_offers (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  property_key VARCHAR(160) NOT NULL DEFAULT '',
  offer_number VARCHAR(80) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL DEFAULT '',
  hidden_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_id),
  INDEX portal_hidden_property_key (property_key)
) ENGINE=InnoDB;

-- New installations also need the incremental offer-translation tables.
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
