<?php
declare(strict_types=1);
// Exercise the real parser without executing the importer's database writes.
$code = file_get_contents(__DIR__ . '/../server/mls/private/bin/import.php');
$main = strpos($code, "\ntry {\n");
if ($main === false) throw new RuntimeException('Importer entry point missing');
eval(substr($code, 5, $main - 5));
$config = require __DIR__ . '/../server/mls/private/config.example.php';
$folder = sys_get_temp_dir() . '/mazur-region-' . bin2hex(random_bytes(6));
mkdir($folder, 0700);
$path = $folder . '/EstiCRM_42_20261001190000.zip';
$offers = '<offers export="full">';
foreach (['MAZOWIECKIE', 'POMORSKIE', 'MAŁOPOLSKIE', ''] as $index => $province) {
    $offers .= '<offer><id>' . ($index + 1) . '</id><action>create</action><status>3</status><offerExport>1</offerExport><locationProvinceName>' . $province . '</locationProvinceName><pictures/></offer>';
}
$offers .= '</offers>';
try {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) throw new RuntimeException('Fixture ZIP creation failed');
    $zip->addFromString('definitions.xml', '<definitions/>');
    $zip->addFromString('offers.xml', $offers);
    $zip->close();
    foreach (['mls' => [false, true, true, true], 'esticrm' => [false, false, false, false]] as $source => $excluded) {
        [$rows] = inspect($path, $config, $source);
        if (count($rows) !== 4) throw new RuntimeException('Full snapshot IDs lost');
        foreach ($excluded as $index => $expected) {
            if (!isset($rows[$index + 1]) || !empty($rows[$index + 1]['out_of_region']) !== $expected) throw new RuntimeException($source . ' region handling failed');
        }
    }
    if (isSupportedRegion(['locationExportProvinceName' => 'POMORSKIE', 'locationProvinceName' => 'MAZOWIECKIE'], 'mls')) throw new RuntimeException('Export province must take priority');
    echo "MLS regional filter and unrestricted selected EstiCRM offers: passed\n";
} finally {
    if (is_file($path)) unlink($path);
    rmdir($folder);
}
