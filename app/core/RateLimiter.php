<?php

class RateLimiter
{
    /**
     * Retorna true se a chave ainda tem cota; false se está bloqueada.
     * Janela deslizante em blocos de RATE_LIMIT_WINDOW segundos.
     */
    public static function hit(string $key, int $max, int $window = RATE_LIMIT_WINDOW): bool
    {
        $keyHash = substr(hash('sha256', $key), 0, 32);
        $bucket  = (int) (time() / $window) * $window;

        Database::execute(
            'DELETE FROM rate_limits WHERE window_start < ?',
            [$bucket - $window * 3]
        );

        $row = Database::fetch(
            'SELECT hits FROM rate_limits WHERE key_hash = ? AND window_start = ?',
            [$keyHash, $bucket]
        );

        if ($row) {
            if ((int) $row['hits'] >= $max) return false;
            Database::execute(
                'UPDATE rate_limits SET hits = hits + 1 WHERE key_hash = ? AND window_start = ?',
                [$keyHash, $bucket]
            );
        } else {
            Database::execute(
                'INSERT OR IGNORE INTO rate_limits (key_hash, hits, window_start) VALUES (?, 1, ?)',
                [$keyHash, $bucket]
            );
        }

        return true;
    }

    public static function remaining(string $key, int $max, int $window = RATE_LIMIT_WINDOW): int
    {
        $keyHash = substr(hash('sha256', $key), 0, 32);
        $bucket  = (int) (time() / $window) * $window;
        $row = Database::fetch(
            'SELECT hits FROM rate_limits WHERE key_hash = ? AND window_start = ?',
            [$keyHash, $bucket]
        );
        $hits = $row ? (int) $row['hits'] : 0;
        return max(0, $max - $hits);
    }
}
