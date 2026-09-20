<?php
/**
 * Create the database and all tables from schema.sql.
 *
 *   php database/install.php
 *
 * Connects without selecting a database first, so it can create it. Reads
 * credentials from config/config.php.
 */

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$configPath = dirname(__DIR__) . '/config/config.php';

if (!is_file($configPath)) {
    exit("Missing config/config.php.\nCopy config/config.example.php to config/config.php first.\n");
}

$config = require $configPath;
$db     = $config['db'];

$schemaPath = __DIR__ . '/schema.sql';

if (!is_file($schemaPath)) {
    exit("Missing database/schema.sql\n");
}

echo "Connecting to MySQL at {$db['host']}:{$db['port']}...\n";

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    exit("Could not connect: {$e->getMessage()}\n\nIs MySQL running? Check the credentials in config/config.php.\n");
}

$sql = file_get_contents($schemaPath);

// Strip line comments so they cannot swallow a statement terminator.
$sql = preg_replace('/^\s*--.*$/m', '', $sql);

// Split on semicolons at end of line. The schema deliberately contains no
// stored routines, so there are no BEGIN...END blocks to worry about.
$statements = array_filter(
    array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)),
    static fn($s) => $s !== ''
);

echo "Running " . count($statements) . " statements from schema.sql...\n";

$executed = 0;
foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        $executed++;
    } catch (PDOException $e) {
        $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 90);
        exit("\nFailed on statement " . ($executed + 1) . ":\n  {$preview}...\n\n  {$e->getMessage()}\n");
    }
}

echo "\nSchema installed into `{$db['database']}` ({$executed} statements).\n";
echo "Next: php database/seed.php\n";
