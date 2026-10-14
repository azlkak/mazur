-- Existing selections remain MLS offers in their current display order.
-- Run once before deploying the source-aware featured endpoints.
ALTER TABLE featured_offers
  ADD COLUMN source VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'mls' FIRST,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (source, source_id);
