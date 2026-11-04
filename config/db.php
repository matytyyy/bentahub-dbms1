<?php
// Reads secrets from the local .env file (never committed) and opens the PDO connection.
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\"'");
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$db   = $_ENV['DB_NAME'] ?? 'bentahub_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>BentaHub setup</title>'
       . '<body style="font-family:system-ui;max-width:560px;margin:15vh auto;padding:0 20px;line-height:1.6">'
       . '<h2>Cannot reach the database</h2>'
       . '<p>Check that MySQL is running, that <code>.env</code> exists (copy <code>.env.example</code>) '
       . 'and that you ran <code>schema.sql</code>, the three <code>features_*.sql</code> files and <code>seed.sql</code> in that order.</p>'
       . '<p style="color:#666">Technical detail: ' . htmlspecialchars($e->getMessage()) . '</p></body>';
    exit;
}
