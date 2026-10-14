-- Manual portal prices are separate from imported data, so the next import cannot erase them.
CREATE TABLE IF NOT EXISTS portal_offer_prices (
  source VARCHAR(16) CHARACTER SET ascii NOT NULL,
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  price DECIMAL(15,2) NOT NULL,
  updated_by VARCHAR(254) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_id)
) ENGINE=InnoDB;
