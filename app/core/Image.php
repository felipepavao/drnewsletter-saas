<?php

/**
 * Upload de imagem com validação MIME + redimensionamento/crop central via GD.
 * Saída sempre em WebP para normalizar storage.
 */
class Image
{
    /**
     * @param array       $file    Item de $_FILES
     * @param string      $subdir  'photos' ou 'banners' (pasta em /public/uploads/)
     * @param int         $width   largura final
     * @param int         $height  altura final
     * @param int         $userId  id do usuário (pra nome do arquivo)
     * @param string|null $oldPath caminho antigo (será deletado do disco)
     * @return string|null Caminho relativo público (ex.: /uploads/photos/u12_...webp) ou null em erro
     */
    public static function uploadAndResize(
        array   $file,
        string  $subdir,
        int     $width,
        int     $height,
        int     $userId,
        ?string $oldPath = null
    ): ?string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Flash::error('Erro no upload da imagem.');
            return null;
        }
        if ($file['size'] > UPLOAD_MAX_BYTES) {
            Flash::error('Imagem muito grande (máx ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . 'MB).');
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        if (!in_array($mime, $allowed, true)) {
            Flash::error('Formato inválido. Use JPG, PNG, WebP ou HEIC.');
            return null;
        }

        if (!function_exists('imagecreatetruecolor')) {
            Flash::error('Servidor sem suporte a GD. Avise o administrador.');
            return null;
        }

        $src = self::loadSource($file['tmp_name'], $mime);
        if (!$src) {
            Flash::error('Não foi possível processar a imagem.');
            return null;
        }

        $resized = self::resizeCrop($src, $width, $height);
        imagedestroy($src);

        $dir = UPLOAD_PATH . '/' . $subdir;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'u' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.webp';
        $path = $dir . '/' . $filename;

        if (!imagewebp($resized, $path, IMAGE_QUALITY)) {
            imagedestroy($resized);
            Flash::error('Erro ao salvar a imagem.');
            return null;
        }
        imagedestroy($resized);

        // Remove arquivo antigo
        if ($oldPath) self::delete($oldPath);

        return '/uploads/' . $subdir . '/' . $filename;
    }

    public static function delete(?string $publicPath): void
    {
        if (!$publicPath) return;
        $full = APP_ROOT . '/public' . $publicPath;
        if (is_file($full) && str_starts_with(realpath($full) ?: '', realpath(UPLOAD_PATH) ?: '')) {
            @unlink($full);
        }
    }

    // ---------- Internals ----------

    private static function loadSource(string $tmp, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            'image/heic', 'image/heif' => self::convertHeic($tmp),
            default      => false,
        };
    }

    private static function convertHeic(string $tmp)
    {
        // GD não lê HEIC nativo. Tentamos usar Imagick se disponível.
        if (!extension_loaded('imagick')) return false;
        try {
            $im = new Imagick($tmp);
            $im->setImageFormat('jpeg');
            $blob = $im->getImageBlob();
            $im->destroy();
            return @imagecreatefromstring($blob);
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function resizeCrop($src, int $w, int $h)
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $srcRatio = $sw / $sh;
        $dstRatio = $w / $h;

        // Crop central: recorta o src pra bater com o ratio de destino, depois redimensiona.
        if ($srcRatio > $dstRatio) {
            $cropW = (int) round($sh * $dstRatio);
            $cropH = $sh;
            $cropX = (int) round(($sw - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $sw;
            $cropH = (int) round($sw / $dstRatio);
            $cropX = 0;
            $cropY = (int) round(($sh - $cropH) / 2);
        }

        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $w, $h, $cropW, $cropH);
        return $dst;
    }
}
