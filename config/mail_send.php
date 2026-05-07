<?php
declare(strict_types=1);

/**
 * Envío de correo: SMTP (recomendado en servidor / dominio corporativo) o mail() de PHP.
 *
 * conexion.local.php: smtp_host, smtp_port, smtp_encryption (ssl|tls|''), smtp_user, smtp_pass,
 * mail_from, mail_from_name. Alias: from_email, from_name, smtp_username, username, password
 * (password solo si smtp_pass está vacío; no use la clave "host" para el SMTP en este mismo
 * array: chocaría con la base de datos — usar siempre smtp_host).
 */

if (!function_exists('lm_mail_app_config')) {
    function lm_mail_app_config(): array
    {
        return $GLOBALS['_lm_app_config'] ?? [];
    }
}

if (!function_exists('lm_mail_encode_subject')) {
    function lm_mail_encode_subject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject) === 1) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }

        return $subject;
    }
}

if (!function_exists('lm_mail_smtp_read')) {
    function lm_mail_smtp_read($fp): string
    {
        $buf = '';
        while (($line = fgets($fp, 8192)) !== false) {
            $buf .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return $buf;
    }
}

if (!function_exists('lm_mail_smtp_expect')) {
    /**
     * @param resource $fp
     * @param list<int> $codes
     */
    function lm_mail_smtp_expect($fp, array $codes, string $ctx): void
    {
        $r = lm_mail_smtp_read($fp);
        $code = (int) substr($r, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException($ctx . ': ' . trim($r));
        }
    }
}

if (!function_exists('lm_mail_send_smtp')) {
    /**
     * @param array<string, mixed> $c
     */
    function lm_mail_send_smtp(array $c, string $to, string $subject, string $bodyText, string $fromEmail, string $fromName): void
    {
        $host = trim((string) ($c['smtp_host'] ?? ''));
        $port = (int) ($c['smtp_port'] ?? 587);
        $enc = strtolower(trim((string) ($c['smtp_encryption'] ?? 'tls')));
        $user = trim((string) ($c['smtp_user'] ?? $c['smtp_username'] ?? $c['username'] ?? ''));
        $pass = (string) ($c['smtp_pass'] ?? '');
        if ($pass === '' && isset($c['password']) && (string) $c['password'] !== '') {
            $pass = (string) $c['password'];
        }

        if ($host === '') {
            throw new RuntimeException('smtp_host no configurado');
        }

        $verifySsl = !isset($c['smtp_verify_peer']) || filter_var($c['smtp_verify_peer'], FILTER_VALIDATE_BOOLEAN);
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
                'allow_self_signed' => !$verifySsl,
            ],
        ]);

        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
        if ($fp === false) {
            throw new RuntimeException('No se pudo conectar al SMTP: ' . $errstr . " ({$errno})");
        }
        stream_set_timeout($fp, 25);

        lm_mail_smtp_expect($fp, [220], 'SMTP greeting');

        $ehloId = gethostname() ?: 'localhost';
        fwrite($fp, 'EHLO ' . $ehloId . "\r\n");
        lm_mail_smtp_expect($fp, [250], 'EHLO');

        if ($enc === 'tls') {
            fwrite($fp, "STARTTLS\r\n");
            lm_mail_smtp_expect($fp, [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Fallo STARTTLS');
            }
            fwrite($fp, 'EHLO ' . $ehloId . "\r\n");
            lm_mail_smtp_expect($fp, [250], 'EHLO tras TLS');
        }

        if ($user !== '' && $pass !== '') {
            fwrite($fp, "AUTH LOGIN\r\n");
            lm_mail_smtp_expect($fp, [334], 'AUTH LOGIN');
            fwrite($fp, base64_encode($user) . "\r\n");
            lm_mail_smtp_expect($fp, [334], 'AUTH user');
            fwrite($fp, base64_encode($pass) . "\r\n");
            lm_mail_smtp_expect($fp, [235], 'AUTH pass');
        }

        fwrite($fp, 'MAIL FROM:<' . $fromEmail . ">\r\n");
        lm_mail_smtp_expect($fp, [250], 'MAIL FROM');

        fwrite($fp, 'RCPT TO:<' . $to . ">\r\n");
        lm_mail_smtp_expect($fp, [250, 251], 'RCPT TO');

        fwrite($fp, "DATA\r\n");
        lm_mail_smtp_expect($fp, [354], 'DATA');

        $fromHdr = $fromName !== ''
            ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>'
            : $fromEmail;

        $mime = "From: {$fromHdr}\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . lm_mail_encode_subject($subject) . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n"
            . "\r\n"
            . str_replace("\n", "\r\n", str_replace("\r\n", "\n", $bodyText)) . "\r\n";

        $mime = preg_replace("/\r\n\./m", "\r\n..", $mime);
        fwrite($fp, $mime . ".\r\n");
        lm_mail_smtp_expect($fp, [250], 'mensaje');

        fwrite($fp, "QUIT\r\n");
        fclose($fp);
    }
}

if (!function_exists('lm_mail_send_app')) {
    /**
     * @return array{ok: bool, error?: string}
     */
    function lm_mail_send_app(string $to, string $subject, string $bodyText): array
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Correo destino no válido'];
        }

        $c = lm_mail_app_config();
        $from = trim((string) ($c['mail_from'] ?? $c['from_email'] ?? ''));
        $fromName = trim((string) ($c['mail_from_name'] ?? $c['from_name'] ?? 'LogiMeat'));

        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'mail_from no configurado o no válido en conexion.local.php'];
        }

        $useSmtp = trim((string) ($c['smtp_host'] ?? '')) !== '';

        try {
            if ($useSmtp) {
                lm_mail_send_smtp($c, $to, $subject, $bodyText, $from, $fromName);
            } else {
                $subjHdr = lm_mail_encode_subject($subject);
                $headers = "From: {$from}\r\n"
                    . "MIME-Version: 1.0\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: 8bit\r\n";
                if (!@mail($to, $subjHdr, $bodyText, $headers)) {
                    return ['ok' => false, 'error' => 'mail() devolvió false (revise sendmail/SMTP del servidor)'];
                }
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true];
    }
}
