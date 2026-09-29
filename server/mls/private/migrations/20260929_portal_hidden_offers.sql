-- A portal-only visibility override. MLS/EstiCRM imports never remove these rows.
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
