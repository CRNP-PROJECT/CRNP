<?php

namespace App\Support;

/**
 * Upload — hardened file uploads and image URL helpers.
 */
class Upload {

    public static function save(string $field, string $destDir, array $allowed = ['jpg', 'jpeg', 'png', 'webp'], int $maxMB = 5): ?string {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Upload error (code ' . $_FILES[$field]['error'] . ').');
        }
        if ($_FILES[$field]['size'] > $maxMB * 1024 * 1024) {
            throw new \Exception("File exceeds {$maxMB}MB limit.");
        }
        $tmp = $_FILES[$field]['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($mimeToExt[$mime])) {
            throw new \Exception('Invalid file type. Only images are allowed.');
        }
        $ext = $mimeToExt[$mime];
        if (!in_array($ext, $allowed)) {
            throw new \Exception('File type not permitted.');
        }
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false) {
            throw new \Exception('File is not a valid image.');
        }
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        $name   = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = rtrim($destDir, '/') . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new \Exception('Failed to save uploaded file.');
        }
        return $name;
    }

    public static function web(string $category, ?string $filename): string {
        if (!$filename) {
            return '/assets/img/placeholder.svg';
        }
        if (preg_match('#^https?://#i', $filename)) {
            return $filename;
        }
        if (str_starts_with($filename, 'b64:')) {
            return $filename;
        }
        if ($category === 'admin/item') {
            return '/assets/img/products/' . rawurlencode($filename);
        }
        return UPLOAD_WEB . '/' . $category . '/' . rawurlencode($filename);
    }

    public static function toBase64(string $field, string $localDir = '', int $maxMB = 5): ?string {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Upload error (code ' . $_FILES[$field]['error'] . ').');
        }
        if ($_FILES[$field]['size'] > $maxMB * 1024 * 1024) {
            throw new \Exception("File exceeds {$maxMB}MB limit.");
        }
        $tmp = $_FILES[$field]['tmp_name'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($mimeToExt[$mime])) {
            throw new \Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
        }
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false) {
            throw new \Exception('File is not a valid image.');
        }

        $bytes = file_get_contents($tmp);
        if ($bytes === false) {
            throw new \Exception('Failed to read uploaded file.');
        }

        if ($localDir !== '') {
            $ext  = $mimeToExt[$mime];
            $name = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!is_dir($localDir)) {
                @mkdir($localDir, 0775, true);
            }
            if (!move_uploaded_file($tmp, rtrim($localDir, '/') . '/' . $name)) {
                throw new \Exception('Failed to save uploaded file locally.');
            }
        }

        return 'b64:' . base64_encode($bytes);
    }

    public static function displaySrc(?string $image, string $legacyDir = 'admin/item'): string {
        if (!$image || $image === '') {
            return '/assets/img/placeholder.svg';
        }
        if (strncmp($image, 'b64:', 4) === 0) {
            $raw = base64_decode(substr($image, 4), true);
            if ($raw === false || strlen($raw) < 8) {
                return '/assets/img/placeholder.svg';
            }
            $mime = 'image/jpeg';
            if (str_starts_with($raw, "\x89PNG\r\n\x1a\n")) {
                $mime = 'image/png';
            } elseif (str_starts_with($raw, 'RIFF') && substr($raw, 8, 4) === 'WEBP') {
                $mime = 'image/webp';
            } elseif (str_starts_with($raw, "\xff\xd8\xff")) {
                $mime = 'image/jpeg';
            } elseif (str_starts_with($raw, 'GIF87a') || str_starts_with($raw, 'GIF89a')) {
                $mime = 'image/gif';
            }
            return 'data:' . $mime . ';base64,' . substr($image, 4);
        }
        return self::web($legacyDir, $image);
    }

    public static function productImageUrl(?string $image, string $id, string $table = 'products'): string {
        if (!$image || $image === '') {
            return '/assets/img/placeholder.svg';
        }
        if (str_starts_with($image, 'b64:')) {
            return '/user/product_image.php?id=' . rawurlencode($id) . '&table=' . rawurlencode($table);
        }
        return self::displaySrc($image);
    }
}
