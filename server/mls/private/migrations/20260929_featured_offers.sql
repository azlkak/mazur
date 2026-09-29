-- Independent of the planned two-source MLS/EstiCRM migration.
-- Only numeric MLS record IDs are accepted by the first version of the panel.
CREATE TABLE IF NOT EXISTS featured_offers (
  source_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
  display_order TINYINT UNSIGNED NOT NULL,
  selected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (source_id),
  UNIQUE KEY featured_display_order (display_order)
) ENGINE=InnoDB;
