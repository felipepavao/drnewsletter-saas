<?php

/**
 * Log estruturado em JSON Lines. Uma linha por evento, parseável por jq/grep/logtail.
 *
 * Uso:
 *   Log::info('user.approved',  ['target_id' => 7, 'by' => 3]);
 *   Log::warn('rate_limit.hit', ['key' => 'req_code:ip:...', 'max' => 10]);
 *   Log::error('mail.failed',   ['to' => $email, 'backend' => 'brevo', 'http' => 500]);
 *
 * Formato:
 *   {"at":"2026-04-17T10:30:00+00:00","lvl":"info","ev":"user.approved",
 *    "ctx":{...}, "req":{"ip":"...","m":"POST","u":"/admin/..."}, "uid":3, "sid":"..."}
 */
class Log
{
    public const DEBUG = 10;
    public const INFO  = 20;
    public const WARN  = 30;
    public const ERROR = 40;

    private const LEVEL_NAMES = [
        self::DEBUG => 'debug',
        self::INFO  => 'info',
        self::WARN  => 'warn',
        self::ERROR => 'error',
    ];

    public static function debug(string $event, array $context = []): void { self::write(self::DEBUG, $event, $context); }
    public static function info(string $event,  array $context = []): void { self::write(self::INFO,  $event, $context); }
    public static function warn(string $event,  array $context = []): void { self::write(self::WARN,  $event, $context); }
    public static function error(string $event, array $context = []): void { self::write(self::ERROR, $event, $context); }

    private static function write(int $level, string $event, array $context): void
    {
        // Em local, só o minimum — reduz ruído no dev.
        $minLevel = APP_ENV === 'local' ? self::INFO : self::INFO;
        if ($level < $minLevel) return;

        $line = [
            'at'  => date('c'),
            'lvl' => self::LEVEL_NAMES[$level] ?? 'info',
            'ev'  => $event,
        ];
        if ($context) $line['ctx'] = self::sanitize($context);

        if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) {
            $line['req'] = [
                'ip' => Request::ip(),
                'm'  => Request::method(),
                'u'  => Request::uri(),
            ];
            $uid = Session::userId();
            if ($uid) $line['uid'] = $uid;
        }

        $file = LOG_DIR . '/' . self::fileFor($level);
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);

        @file_put_contents(
            $file,
            json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /** Errors vão em error.log; o resto em app.log. */
    private static function fileFor(int $level): string
    {
        return $level >= self::ERROR ? 'error.log' : 'app.log';
    }

    /**
     * Sanitiza contexto: remove chaves sensíveis (password, token, code, secret, key)
     * e trunca strings enormes pra log não inchar.
     */
    private static function sanitize(array $ctx): array
    {
        $sensitive = ['password', 'token', 'code', 'secret', 'api_key', 'apikey', 'authorization'];
        $out = [];
        foreach ($ctx as $k => $v) {
            $kl = strtolower((string) $k);
            if (in_array($kl, $sensitive, true)) {
                $out[$k] = '[REDACTED]';
                continue;
            }
            if (is_string($v) && strlen($v) > 1000) {
                $out[$k] = substr($v, 0, 1000) . '…[trunc]';
                continue;
            }
            if (is_array($v)) {
                $out[$k] = self::sanitize($v);
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }
}
