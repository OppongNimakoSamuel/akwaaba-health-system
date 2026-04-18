<?php

function getDB()
{

    // Load .env manually (simple version)
    $env = parse_ini_file(__DIR__ . '/.env');

    $host = $env['DB_HOST'] ?? 'localhost';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';
    $dbname = $env['DB_NAME'] ?? 'akwaaba';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8",
            $user,
            $pass
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;

    } catch (PDOException $e) {
        die("DB ERROR: " . $e->getMessage());
    }
}