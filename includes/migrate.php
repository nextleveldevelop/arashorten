<?php
declare(strict_types=1);

require_once __DIR__ . '/schema.php';

function dbColumnExists(string $table, string $column): bool
{
    $statement = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $statement->execute(['t' => $table, 'c' => $column]);

    return (int) $statement->fetchColumn() > 0;
}

/**
 * Idempotent: safe to run any number of times.
 */
function runMigrations(): void
{
    $pdo = db();

    foreach (schemaTables() as $sql) {
        $pdo->exec($sql);
    }

    foreach (schemaColumnUpgrades() as [$table, $column, $alter]) {
        if (!dbColumnExists($table, $column)) {
            $pdo->exec('ALTER TABLE ' . $table . ' ' . $alter);
        }
    }

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:k, :v)'
    );

    foreach (schemaDefaultSettings() as $key => $value) {
        $insert->execute(['k' => $key, 'v' => $value]);
    }

    // Upgrade from v2.0/v2.1: there were no roles, so nobody is an admin yet.
    // The oldest account (the site owner who registered first) becomes admin.
    $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    if ($admins === 0) {
        $first = $pdo->query('SELECT id, email FROM users ORDER BY id ASC LIMIT 1')->fetch();

        if ($first) {
            $update = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id");
            $update->execute(['id' => $first['id']]);

            error_log('AraShorten upgrade: account #' . $first['id'] . ' promoted to admin.');
        }
    }

    $insert = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $insert->execute(['k' => 'schema_version', 'v' => (string) SCHEMA_VERSION]);
}

/**
 * Upgrade the database automatically the first time new code runs.
 */
function ensureSchema(): void
{
    if ((int) (allSettings()['schema_version'] ?? 0) >= SCHEMA_VERSION) {
        return;
    }

    try {
        $lock = db()->query("SELECT GET_LOCK('arashorten_migrate', 15)")->fetchColumn();

        // Another request may have finished the upgrade while we waited.
        if ((int) (allSettings(true)['schema_version'] ?? 0) < SCHEMA_VERSION) {
            runMigrations();
            allSettings(true);
        }

        if ((int) $lock === 1) {
            db()->query("SELECT RELEASE_LOCK('arashorten_migrate')");
        }
    } catch (Throwable $exception) {
        error_log('Database upgrade failed: ' . $exception->getMessage());

        http_response_code(503);
        exit(
            'تعذرت ترقية قاعدة البيانات تلقائياً. استورد الملف database/schema.sql ' .
            'يدوياً أو شغّل: php cron/upgrade.php'
        );
    }
}
