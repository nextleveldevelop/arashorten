<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_NAME
    );

    try {
        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );

        return $pdo;
    } catch (PDOException $exception) {
        error_log('Database connection failed.');

        http_response_code(503);

        exit('تعذر الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.');
    }
}