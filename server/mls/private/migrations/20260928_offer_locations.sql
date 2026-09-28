-- Run once against u101822986_mls before deploying the updated importer and API.
-- Existing offers are retained; only public city and district names are indexed.
ALTER TABLE mls_offers
  ADD COLUMN location_city VARCHAR(191) NOT NULL DEFAULT '' AFTER publishable,
  ADD COLUMN location_district VARCHAR(191) NOT NULL DEFAULT '' AFTER location_city,
  ADD INDEX idx_search_location (publishable, location_city, location_district);

UPDATE mls_offers
SET location_city = TRIM(COALESCE(
      NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fields_json, '$.locationExportCityName')), ''),
      JSON_UNQUOTE(JSON_EXTRACT(fields_json, '$.locationCityName')), '')),
    location_district = TRIM(COALESCE(
      NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fields_json, '$.locationExportPrecinctName')), ''),
      JSON_UNQUOTE(JSON_EXTRACT(fields_json, '$.locationPrecinctName')), ''));

-- The first count should be zero; inspect any exceptions before enabling the endpoint.
SELECT COUNT(*) AS publishable_without_city
FROM mls_offers WHERE publishable = 1 AND location_city = '';
SELECT COUNT(DISTINCT location_city) AS searchable_cities
FROM mls_offers WHERE publishable = 1 AND location_city <> '';
