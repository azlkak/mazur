<?php
declare(strict_types=1);

$name = (string)($_GET['name'] ?? '');
if (!preg_match('/\A[a-f0-9]{64}\.(?:jpg|png)\z/', $name)) {
    http_response_code(404);
    exit;
}

$imageRoot = dirname(__DIR__, 2) . '/mls/images';
$path = $imageRoot . '/' . $name;
if (!is_file($path) || is_link($path)) {
    http_response_code(404);
    exit;
}

$type = str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg';
$width = (string)($_GET['w'] ?? '');
if ($width !== '' && !in_array($width, ['800', '1200'], true)) {
    http_response_code(400);
    exit;
}

function serveImage(string $file, string $mime, bool $immutable = true): void
{
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($file));
    // Original names are SHA-256 hashes; each resized variant is derived from one hash.
    header('Cache-Control: ' . ($immutable ? 'public, max-age=31536000, immutable' : 'public, max-age=300'));
    header('X-Content-Type-Options: nosniff');
    readfile($file);
}

if ($width === '') {
    serveImage($path, $type);
    exit;
}

// Browsers without WebP support keep receiving the untouched original.
header('Vary: Accept');
if (!str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'image/webp') ||
    !function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
    serveImage($path, $type, false);
    exit;
}

$variant = $imageRoot . '/' . substr($name, 0, 64) . '-w' . $width . '.webp';
if (!is_file($variant) && !is_link($variant)) {
    $temporary = false;
    try {
        $info = @getimagesize($path);
        // Bound the first-request conversion cost. Large or unusual files use the original.
        if ($info && $info[0] > 0 && $info[1] > 0 && $info[0] * $info[1] <= 12000000 &&
            in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            $orientation = 1;
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path, 'IFD0', true, false);
                if (is_array($exif)) $orientation = (int)($exif['IFD0']['Orientation'] ?? 1);
            }
            // Avoid changing the orientation of photos that rely on EXIF rotation.
            if ($orientation === 1) {
                $source = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
                if ($source !== false) {
                    $targetWidth = min((int)$width, $info[0]);
                    $targetHeight = max(1, (int)round($info[1] * $targetWidth / $info[0]));
                    $target = imagecreatetruecolor($targetWidth, $targetHeight);
                    if ($target !== false) {
                        imagealphablending($target, false);
                        imagesavealpha($target, true);
                        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                        imagefill($target, 0, 0, $transparent);
                        if (imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $info[0], $info[1])) {
                            $temporary = tempnam($imageRoot, '.thumb-');
                            if ($temporary !== false && @imagewebp($target, $temporary, 80) && filesize($temporary) > 0) {
                                // A concurrent request may have produced the same immutable file.
                                if (!is_file($variant)) @rename($temporary, $variant);
                            }
                        }
                    }
                }
            }
        }
    } catch (Throwable $ignored) {
        // A conversion failure must never hide a valid original image.
    } finally {
        if ($temporary !== false && is_file($temporary)) @unlink($temporary);
    }
}

if (is_file($variant) && !is_link($variant)) serveImage($variant, 'image/webp');
else serveImage($path, $type, false);
