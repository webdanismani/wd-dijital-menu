<?php

declare(strict_types=1);

/**
 * Ürün, kampanya ve logo görselleri. Yalnızca gerçek JPG/PNG/WebP dosyaları kabul edilir,
 * rastgele adla uploads/ klasörüne kaydedilir. Klasörde PHP çalıştırma kapalıdır (.htaccess).
 */
final class Upload
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public static function dir(): string
    {
        return APP_ROOT . '/uploads';
    }

    /**
     * @return array{ok:bool,file:string,error:string}
     */
    public static function save(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'file' => '', 'error' => ''];
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'file' => '', 'error' => t($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE ? 'upload.too_big' : 'upload.failed')];
        }
        if ($f['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'file' => '', 'error' => t('upload.too_big')];
        }
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !isset(self::TYPES[$info[2]])) {
            return ['ok' => false, 'file' => '', 'error' => t('upload.bad_type')];
        }
        if (!is_dir(self::dir()) && !@mkdir(self::dir(), 0755, true)) {
            return ['ok' => false, 'file' => '', 'error' => t('upload.not_writable')];
        }
        $name = date('Ymd') . '-' . random_token(6) . '.' . self::TYPES[$info[2]];
        if (!move_uploaded_file($f['tmp_name'], self::dir() . '/' . $name)) {
            return ['ok' => false, 'file' => '', 'error' => t('upload.not_writable')];
        }
        @chmod(self::dir() . '/' . $name, 0644);
        return ['ok' => true, 'file' => $name, 'error' => ''];
    }

    public static function delete(string $file): void
    {
        if ($file === '' || !preg_match('/^[0-9]{8}-[a-f0-9]{12}\.(jpg|png|webp)$/', $file)) {
            return;
        }
        // Aynı görsel başka kayıtta kullanılıyorsa silinmez.
        $used = (int) DB::val('SELECT COUNT(*) FROM {products} WHERE image = ?', [$file])
            + (int) DB::val('SELECT COUNT(*) FROM {campaigns} WHERE image = ?', [$file])
            + (DB::setting('logo', '') === $file ? 1 : 0);
        if ($used === 0) {
            @unlink(self::dir() . '/' . $file);
        }
    }
}
