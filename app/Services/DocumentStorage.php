<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\HTTP\Files\UploadedFile;
final class DocumentStorage
{
    private const DOCUMENT_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];
    public function store(UploadedFile $file, int $childId, bool $photo = false): array
    {
        if (! $file->isValid() || $file->hasMoved()) throw new \RuntimeException('Please choose a valid file.');
        $limit = $photo ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
        if ($file->getSize() > $limit) throw new \RuntimeException('The selected file is larger than the allowed size.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getTempName()) ?: '';
        $allowed = $photo ? ['image/jpeg', 'image/png'] : self::DOCUMENT_MIMES;
        if (! in_array($mime, $allowed, true)) throw new \RuntimeException('Only PDF, JPG, and PNG files are accepted.');
        $extension = match ($mime) { 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', default => throw new \RuntimeException('Unsupported file type.') };
        $folder = WRITEPATH . 'uploads/children/' . $childId;
        if (! is_dir($folder) && ! mkdir($folder, 0700, true) && ! is_dir($folder)) throw new \RuntimeException('Private upload storage is unavailable.');
        $stored = bin2hex(random_bytes(20)) . '.' . $extension;
        if (! $file->move($folder, $stored)) throw new \RuntimeException('Unable to store the uploaded file.');
        $path = $folder . DIRECTORY_SEPARATOR . $stored;
        if ($photo) {
            try {
                service('image')->withFile($path)->resize(600, 600, true, 'auto')->save($path);
            } catch (\Throwable $e) {
                @unlink($path);
                throw new \RuntimeException('Photo resizing is unavailable. Enable the PHP GD extension and retry.', 0, $e);
            }
        }
        @chmod($path, 0600);
        return ['stored_name' => $stored, 'original_name' => basename($file->getClientName()), 'mime_type' => $mime, 'size_bytes' => (int) filesize($path), 'path' => $path];
    }
    public function path(int $childId, string $storedName): string
    {
        if (! preg_match('/^[a-f0-9]{40}\.(pdf|jpg|png)$/', $storedName)) throw new \RuntimeException('Invalid stored filename.');
        $path = WRITEPATH . 'uploads/children/' . $childId . '/' . $storedName;
        if (! is_file($path)) throw new \CodeIgniter\Exceptions\PageNotFoundException('Document not found.');
        return $path;
    }
}
