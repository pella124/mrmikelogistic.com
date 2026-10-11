<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$name = trim((string)($argv[1] ?? ''));
$email = strtolower(trim((string)($argv[2] ?? '')));
$password = (string)($argv[3] ?? '');
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Usage: php create-admin.php \"Admin name\" admin@example.com \"password-at-least-12-chars\"\n");
    exit(1);
}
$stmt = db()->prepare('INSERT INTO admins (name,email,password_hash) VALUES (?,?,?)');
$stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Admin account created for {$email}\n");
