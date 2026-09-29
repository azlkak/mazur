-- Keep historical rows and images, but remove offers outside Mazowieckie
-- (including rows without a province) from every public API.
-- Run after installing the matching importer so hourly updates cannot undo it.
UPDATE mls_offers
SET publishable = 0
WHERE publishable = 1
  AND UPPER(COALESCE(
      NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(fields_json, '$.locationExportProvinceName'))), ''),
      NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(fields_json, '$.locationProvinceName'))), ''),
      ''
  )) <> 'MAZOWIECKIE';

-- Must return zero after the update.
SELECT COUNT(*) AS published_outside_mazowieckie
FROM mls_offers
WHERE publishable = 1
  AND UPPER(COALESCE(
      NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(fields_json, '$.locationExportProvinceName'))), ''),
      NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(fields_json, '$.locationProvinceName'))), ''),
      ''
  )) <> 'MAZOWIECKIE';
