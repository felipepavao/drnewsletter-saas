<?php

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dir = dirname(DB_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            self::$instance = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            self::$instance->exec('PRAGMA journal_mode = WAL');
            self::$instance->exec('PRAGMA foreign_keys = ON');
            self::$instance->exec('PRAGMA busy_timeout = 5000');
        }

        return self::$instance;
    }

    public static function runMigrations(): void
    {
        $db = self::getInstance();

        $db->exec('CREATE TABLE IF NOT EXISTS _migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL UNIQUE,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $applied = $db->query('SELECT filename FROM _migrations')->fetchAll(PDO::FETCH_COLUMN);
        $migrationDir = APP_ROOT . '/migrations';
        if (!is_dir($migrationDir)) {
            return;
        }

        $files = glob($migrationDir . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $applied, true)) continue;

            $sql = file_get_contents($file);
            $db->beginTransaction();
            try {
                $db->exec($sql);
                $stmt = $db->prepare('INSERT INTO _migrations (filename) VALUES (?)');
                $stmt->execute([$filename]);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw new RuntimeException("Migration {$filename} falhou: " . $e->getMessage(), 0, $e);
            }
        }
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $r = self::query($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = [], int $col = 0)
    {
        return self::query($sql, $params)->fetchColumn($col);
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    public static function transaction(callable $fn)
    {
        $db = self::getInstance();
        $db->beginTransaction();
        try {
            $result = $fn($db);
            $db->commit();
            return $result;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
