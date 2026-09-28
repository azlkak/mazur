CREATE TABLE IF NOT EXISTS mls_batches (
  sha256 CHAR(64) CHARACTER SET ascii PRIMARY KEY,
  file_name VARCHAR(255) NOT NULL,
  export_type ENUM('incremental','full') NOT NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  offer_count INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mls_offers (
  source_id VARCHAR(40) CHARACTER SET ascii PRIMARY KEY,
  source_export_at DATETIME NOT NULL,
  batch_sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
  publishable BOOLEAN NOT NULL DEFAULT FALSE,
  location_city VARCHAR(191) NOT NULL DEFAULT '',
  location_district VARCHAR(191) NOT NULL DEFAULT '',
  fields_json LONGTEXT NOT NULL,
  images_json LONGTEXT NOT NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (publishable),
  INDEX idx_search_location (publishable, location_city, location_district),
  INDEX (source_export_at)
) ENGINE=InnoDB;
