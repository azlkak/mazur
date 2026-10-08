<?php
return [
    // Keep config.php and this entire directory OUTSIDE public_html.
    'dsn' => 'mysql:host=localhost;dbname=REPLACE;charset=utf8mb4',
    'user' => 'REPLACE',
    'password' => 'REPLACE',
    'enabled' => false,
    'esticrm_import_enabled' => false,
    // VixCRM is a separate full XML snapshot. Enable only after a dry run.
    'vixcrm_import_enabled' => false,
    'vixcrm_feed_url' => '', // keep the private feed URL in config.php only
    'vixcrm_publish_mode' => 'none', // none, selected, all
    'vixcrm_publish_ids' => [], // external IDs when mode=selected
    'max_zip_bytes' => 20 * 1024 ** 3,
    'max_expanded_bytes' => 30 * 1024 ** 3,
    'max_entries' => 200000,
    'max_xml_bytes' => 128 * 1024 ** 2,
    'max_image_bytes' => 20 * 1024 ** 2,
    'min_age_seconds' => 600,
    'min_full_offers' => 1,
    'archive_retention_days' => 7,
    // One summary email per imported package when offer records are skipped.
    'notification_emails' => [],
    // One-time admin codes are sent only to these trusted addresses.
    'featured_admin_emails' => [],
    // EstiCRM: Ustawienia -> Dane firmowe. Never expose these values publicly.
    'esticrm_company' => '',
    'esticrm_token' => '',
    'esticrm_agent_email' => '',
    // Offer translation runs separately after import. Keep the key server-side.
    'openai_api_key' => '',
    'openai_translation_model' => 'gpt-4o-mini',
    'translation_max_jobs_per_run' => 30,
    // Set explicitly if public_html/api is deployed elsewhere.
    'translation_formatter_path' => dirname(__DIR__) . '/public_html/api/description-formatter.php',
];
