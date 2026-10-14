-- One EstiCRM offer per external listing. A pending row is deliberately not
-- retried automatically: the remote create may have succeeded before a timeout.
CREATE TABLE IF NOT EXISTS esticrm_offer_links (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  state ENUM('pending','linked','review') NOT NULL,
  esticrm_id BIGINT UNSIGNED DEFAULT NULL,
  esticrm_number VARCHAR(80) NOT NULL DEFAULT '',
  last_error VARCHAR(120) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_id),
  UNIQUE KEY uniq_esticrm_id (esticrm_id)
) ENGINE=InnoDB;
