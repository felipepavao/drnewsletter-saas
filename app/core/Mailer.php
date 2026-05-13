<?php

/**
 * Envio transacional. Suporta dois backends:
 *   - Brevo HTTP API (preferido, sem dependências)
 *   - SMTP direto (fsockopen, sem PHPMailer) — fallback
 * Se nenhum estiver configurado, apenas logga em data/logs/mail.log.
 */
class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        self::log("[TO: {$to}] [SUBJECT: {$subject}]");

        if (BREVO_API_KEY !== '') {
            return self::sendBrevo($to, $subject, $htmlBody);
        }
        if (SMTP_HOST !== '') {
            return self::sendSmtp($to, $subject, $htmlBody, $textBody);
        }

        self::log('[SKIP] Nenhum backend configurado — email não enviado');
        return true; // Em dev, tratamos como sucesso pra não quebrar fluxos
    }

    public static function sendAuthCode(string $to, string $code): bool
    {
        if (APP_ENV === 'local') {
            self::log("[DEV] auth_code for {$to} = {$code}");
        }
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $html = self::layout(
            '<h1 style="margin:0 0 16px; font-size:22px; color:#2A2A28;">Seu código de acesso</h1>'
            . '<p style="margin:0 0 24px; font-size:16px; color:#5A5A55;">'
            . 'Use o código abaixo para entrar na ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ':'
            . '</p>'
            . '<div style="text-align:center; margin:32px 0;">'
            . '<div style="display:inline-block; padding:20px 32px; background:#FAF8F3; '
            . 'border:1px solid #E5E0D5; border-radius:10px; font-family:Menlo,Consolas,monospace; '
            . 'font-size:32px; font-weight:700; letter-spacing:8px; color:#2A2A28;">'
            . $safeCode . '</div></div>'
            . '<p style="margin:0 0 8px; font-size:14px; color:#7A7A75;">Este código expira em 15 minutos.</p>'
            . '<p style="margin:0; font-size:14px; color:#7A7A75;">Se você não solicitou este acesso, ignore este email.</p>'
        );
        return self::send($to, 'Seu código de acesso — ' . APP_NAME, $html);
    }

    // ---------- Backends ----------

    private static function sendBrevo(string $to, string $subject, string $html): bool
    {
        $payload = json_encode([
            'sender'      => ['name' => FROM_NAME, 'email' => FROM_EMAIL],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . BREVO_API_KEY,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) { self::log('[ERR] Brevo cURL: ' . $err); return false; }
        if ($code !== 201) { self::log("[ERR] Brevo HTTP {$code}: {$resp}"); return false; }
        self::log('[OK] Brevo');
        return true;
    }

    private static function sendSmtp(string $to, string $subject, string $html, ?string $text): bool
    {
        $text ??= strip_tags($html);
        $boundary = 'mlr_' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
            'To: ' . $to,
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'Date: ' . date('r'),
        ];
        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $text . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n\r\n"
            . "--{$boundary}--\r\n";

        try {
            $transport = SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : '';
            $sock = @stream_socket_client(
                $transport . SMTP_HOST . ':' . SMTP_PORT,
                $errno, $errstr, 15,
                STREAM_CLIENT_CONNECT
            );
            if (!$sock) { self::log("[ERR] SMTP connect: {$errstr}"); return false; }

            $read = function () use ($sock) { return fgets($sock, 1024); };
            $write = function (string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

            $read();
            $write('EHLO ' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost'));
            while ($line = $read()) { if (substr($line, 3, 1) !== '-') break; }

            if (SMTP_ENCRYPTION === 'tls') {
                $write('STARTTLS'); $read();
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    self::log('[ERR] SMTP TLS upgrade falhou'); return false;
                }
                $write('EHLO ' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost'));
                while ($line = $read()) { if (substr($line, 3, 1) !== '-') break; }
            }

            if (SMTP_USER !== '') {
                $write('AUTH LOGIN'); $read();
                $write(base64_encode(SMTP_USER)); $read();
                $write(base64_encode(SMTP_PASS)); $read();
            }

            $write('MAIL FROM:<' . FROM_EMAIL . '>'); $read();
            $write('RCPT TO:<' . $to . '>'); $read();
            $write('DATA'); $read();
            fwrite($sock, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
            $read();
            $write('QUIT');
            fclose($sock);
            self::log('[OK] SMTP');
            return true;
        } catch (Throwable $e) {
            self::log('[ERR] SMTP: ' . $e->getMessage());
            return false;
        }
    }

    // ---------- Utilities ----------

    public static function layout(string $content): string
    {
        $site = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
        $year = date('Y');
        return '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#F7F5F0;'
            . 'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">'
            . '<div style="max-width:560px;margin:40px auto;background:#fff;'
            . 'border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">'
            . '<div style="padding:28px 40px;border-bottom:1px solid #EEEAE0;'
            . 'font-family:Georgia,serif;font-size:18px;color:#2A2A28;">' . $site . '</div>'
            . '<div style="padding:40px;color:#2A2A28;line-height:1.55;">' . $content . '</div>'
            . '<div style="padding:20px 40px;background:#FAF8F3;border-top:1px solid #EEEAE0;'
            . 'text-align:center;font-size:12px;color:#9A9A95;">© ' . $year . ' ' . $site . '</div>'
            . '</div></body></html>';
    }

    private static function log(string $line): void
    {
        if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0755, true);
        @file_put_contents(
            LOG_DIR . '/mail.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
