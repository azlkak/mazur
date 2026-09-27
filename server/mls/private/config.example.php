<?php
return [
    // Keep config.php and this entire directory OUTSIDE public_html.
    'dsn' => 'mysql:host=localhost;dbname=REPLACE;charset=utf8mb4',
    'user' => 'REPLACE',
    'password' => 'REPLACE',
    'enabled' => false,
    'max_zip_bytes' => 2 * 1024 ** 3,
    'max_expanded_bytes' => 4 * 1024 ** 3,
    'max_entries' => 200000,
    'max_xml_bytes' => 64 * 1024 ** 2,
    'max_image_bytes' => 20 * 1024 ** 2,
    'min_age_seconds' => 600,
    'min_full_offers' => 1,
    'archive_retention_days' => 7,
];
