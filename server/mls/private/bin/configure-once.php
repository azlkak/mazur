<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$configPath = $root . '/config.php';
$schemaPath = $root . '/schema.sql';

if (is_file($configPath)) {
    fwrite(STDERR, "config.php already exists; refusing to overwrite it.\n");
    exit(1);
}

fwrite(STDOUT, "Database password: ");
system('stty -echo');
$password = rtrim((string) fgets(STDIN), "\r\n");
system('stty echo');
fwrite(STDOUT, "\n");

try {
    if ($password === '') {
        throw new RuntimeException('Password cannot be empty.');
    }
    $dsn = 'mysql:host=localhost;dbname=u101822986_mls;charset=utf8mb4';
    $db = new PDO($dsn, 'u101822986_mls', $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Cannot read schema.sql.');
    }
    $db->exec($schema);

    $config = [
        'dsn' => $dsn,
        'user' => 'u101822986_mls',
        'password' => $password,
        'enabled' => false,
        'max_zip_bytes' => 2 * 1024 ** 3,
        'max_expanded_bytes' => 4 * 1024 ** 3,
        'max_entries' => 200000,
        'max_xml_bytes' => 64 * 1024 ** 2,
        'max_image_bytes' => 20 * 1024 ** 2,
        'min_age_seconds' => 600,
    ];
    $bytes = "<?php\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($configPath, $bytes, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write config.php.');
    }
    chmod($configPath, 0600);
    $password = str_repeat("\0", strlen($password));
    fwrite(STDOUT, "Database configured and schema installed.\n");
    @unlink(__FILE__);
} catch (Throwable $e) {
    $password = str_repeat("\0", strlen($password));
    fwrite(STDERR, "Configuration failed. Check the database password and try again.\n");
    exit(1);
}
