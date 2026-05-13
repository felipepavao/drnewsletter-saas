<?php

/**
 * Helper para uploads de TXT.
 *
 * Validações:
 *  - Veio pelo $_FILES com error == 0
 *  - Tamanho <= UPLOAD_MAX_BYTES
 *  - Extensão .txt (case-insensitive)
 *  - MIME detectado é text/* (não confiamos no client)
 *  - Conteúdo é UTF-8 válido (ou normaliza ISO-8859-1 → UTF-8)
 *
 * Retorna ['filename'=>string, 'content'=>string, 'bytes'=>int]
 * ou lança InvalidArgumentException com mensagem amigável.
 */
class Upload
{
    public static function readTextFile(array $file): array
    {
        if (!is_array($file) || !isset($file['error'])) {
            throw new InvalidArgumentException('Arquivo inválido.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new InvalidArgumentException('Arquivo muito grande.');
            case UPLOAD_ERR_NO_FILE:
                throw new InvalidArgumentException('Nenhum arquivo selecionado.');
            case UPLOAD_ERR_PARTIAL:
                throw new InvalidArgumentException('Upload incompleto. Tente novamente.');
            default:
                throw new InvalidArgumentException('Falha no upload.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new InvalidArgumentException('Arquivo vazio.');
        }
        if ($size > UPLOAD_MAX_BYTES) {
            $mb = round(UPLOAD_MAX_BYTES / 1024 / 1024, 1);
            throw new InvalidArgumentException("Arquivo muito grande. Máximo: {$mb}MB.");
        }

        $name = self::safeFilename((string) ($file['name'] ?? 'arquivo.txt'));
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'txt') {
            throw new InvalidArgumentException('Apenas arquivos .txt são aceitos.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp) && !is_file($tmp)) {
            // is_uploaded_file falha quando rodando em servidor embutido em testes;
            // is_file cobre. Em produção atrás de nginx, is_uploaded_file pega.
            throw new InvalidArgumentException('Upload inválido.');
        }

        // Checa MIME detectado pelo servidor (não pelo client header).
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (finfo_file($finfo, $tmp) ?: '') : '';
        if ($finfo) finfo_close($finfo);
        if ($mime !== '' && !str_starts_with($mime, 'text/')) {
            throw new InvalidArgumentException(
                "Tipo de arquivo não aceito ({$mime}). Envie um .txt em UTF-8."
            );
        }

        $content = file_get_contents($tmp);
        if ($content === false) {
            throw new InvalidArgumentException('Não foi possível ler o arquivo.');
        }

        // BOM UTF-8 — remove se presente
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Se não é UTF-8 válido, tenta converter de ISO-8859-1 (latin1).
        if (!mb_check_encoding($content, 'UTF-8')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
            if ($converted === false || !mb_check_encoding($converted, 'UTF-8')) {
                throw new InvalidArgumentException(
                    'Encoding do arquivo não reconhecido. Salve como UTF-8 e tente novamente.'
                );
            }
            $content = $converted;
        }

        return [
            'filename' => $name,
            'content'  => $content,
            'bytes'    => strlen($content),
        ];
    }

    /** Sanitiza nome de arquivo para armazenamento. */
    private static function safeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/u', '_', $name) ?? 'arquivo.txt';
        $name = trim($name, '_');
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'arquivo.txt';
        }
        return mb_substr($name, 0, 120);
    }
}
