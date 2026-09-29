-- Back up the database first. Run once before deploying the source-aware importer.
-- Existing offers and batches belong to the MLS feed.
ALTER TABLE mls_offers
  ADD COLUMN source VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'mls' FIRST,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (source, source_id);

ALTER TABLE mls_batches
  ADD COLUMN source VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'mls' FIRST,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (source, sha256);
