<?php
// src/media.php
// Helpers para guardar/validar media de partido (imagenes/videos).
// Ruta: src/media.php

function ensure_dir(string $path) {
    if (!is_dir($path)) mkdir($path, 0755, true);
}

function media_config() {
    return [
        'upload_dir' => __DIR__ . '/../public/uploads/matches',
        'max_image_size' => 5 * 1024 * 1024,    // 5 MB
        'max_video_size' => 50 * 1024 * 1024,   // 50 MB
        'allowed_image_mimes' => ['image/jpeg','image/png','image/webp'],
        'allowed_video_mimes' => ['video/mp4','video/webm','video/ogg','video/quicktime'],
        'thumb_width' => 320,
        'thumb_height' => 180,
    ];
}

function safe_basename($name) {
    return preg_replace('/[^A-Za-z0-9_\- \.\,]/u', '_', $name);
}

function random_filename($ext) {
    return bin2hex(random_bytes(12)) . ($ext ? '.' . $ext : '');
}

function create_image_thumbnail($srcPath, $destPath, $maxW, $maxH) {
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    $width = $info[0]; $height = $info[1];
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($srcPath); break;
        case 'image/png':  $img = @imagecreatefrompng($srcPath);  break;
        case 'image/webp': $img = @imagecreatefromwebp($srcPath); break;
        default: return false;
    }
    if (!$img) return false;

    $ratio = min($maxW / $width, $maxH / $height);
    $newW = max(1, (int)($width * $ratio));
    $newH = max(1, (int)($height * $ratio));

    $thumb = imagecreatetruecolor($newW, $newH);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled($thumb, $img, 0,0, 0,0, $newW,$newH, $width,$height);
    ensure_dir(dirname($destPath));
    $saved = imagejpeg($thumb, $destPath, 80);

    imagedestroy($img);
    imagedestroy($thumb);
    return $saved ? $destPath : false;
}

/**
 * save_match_media($_FILES['media'], $match_id, $pdo)
 * Guarda archivos subidos en public/uploads/matches/{match_id}/ y registra en DB.
 * Retorna array ['saved'=>[], 'errors'=>[]]
 */
function save_match_media(array $filesArray, int $match_id, PDO $pdo) : array {
    $cfg = media_config();
    $base = rtrim($cfg['upload_dir'], '/');

    $out = ['saved' => [], 'errors' => []];

    if (!isset($filesArray['name']) || !is_array($filesArray['name'])) {
        return $out;
    }

    $count = count($filesArray['name']);
    for ($i = 0; $i < $count; $i++) {
        $error = $filesArray['error'][$i];
        if ($error !== UPLOAD_ERR_OK) {
            $out['errors'][] = "Fallo upload (slot $i) código $error";
            continue;
        }
        $tmp = $filesArray['tmp_name'][$i];
        $orig = $filesArray['name'][$i];
        $size = (int)$filesArray['size'][$i];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';

        $media_type = 'other';
        if (in_array($mime, $cfg['allowed_image_mimes'])) $media_type = 'image';
        elseif (in_array($mime, $cfg['allowed_video_mimes'])) $media_type = 'video';

        if ($media_type === 'image' && $size > $cfg['max_image_size']) {
            $out['errors'][] = "Imagen demasiado grande: $orig";
            continue;
        }
        if ($media_type === 'video' && $size > $cfg['max_video_size']) {
            $out['errors'][] = "Video demasiado grande: $orig";
            continue;
        }
        if ($media_type === 'other') {
            $out['errors'][] = "Tipo de archivo no permitido: $orig ($mime)";
            continue;
        }

        $ext = pathinfo($orig, PATHINFO_EXTENSION) ?: ($media_type === 'image' ? 'jpg' : 'mp4');
        $stored = random_filename(strtolower(preg_replace('/[^a-z0-9]/', '', $ext)));
        $matchDir = $base . '/' . $match_id;
        ensure_dir($matchDir);
        $dest = $matchDir . '/' . $stored;

        if (!@move_uploaded_file($tmp, $dest)) {
            $out['errors'][] = "No se pudo mover $orig";
            continue;
        }

        $thumb_path = null;
        if ($media_type === 'image') {
            $thumbName = pathinfo($stored, PATHINFO_FILENAME) . '_thumb.jpg';
            $thumbFull = $matchDir . '/' . $thumbName;
            if (create_image_thumbnail($dest, $thumbFull, $cfg['thumb_width'], $cfg['thumb_height'])) {
                $thumb_path = 'uploads/matches/' . $match_id . '/' . $thumbName;
            }
        } elseif ($media_type === 'video') {
            // opcional: generar poster con ffmpeg si disponible
            // $posterName = pathinfo($stored, PATHINFO_FILENAME) . '_poster.jpg';
            // $posterFull = $matchDir . '/' . $posterName;
            // $cmd = "ffmpeg -i " . escapeshellarg($dest) . " -ss 00:00:01.000 -vframes 1 -q:v 2 " . escapeshellarg($posterFull);
            // exec($cmd, $outExec, $rc);
            // if ($rc === 0) $thumb_path = 'uploads/matches/' . $match_id . '/' . $posterName;
        }

        $stmt = $pdo->prepare("INSERT INTO match_media (match_id, file_name, original_name, mime_type, size, media_type, thumb_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $match_id,
            'uploads/matches/' . $match_id . '/' . $stored,
            safe_basename($orig),
            $mime,
            $size,
            $media_type,
            $thumb_path
        ]);
        $id = (int)$pdo->lastInsertId();

        $out['saved'][] = [
            'id' => $id,
            'path' => 'uploads/matches/' . $match_id . '/' . $stored,
            'original' => safe_basename($orig),
            'mime' => $mime,
            'thumb' => $thumb_path,
            'media_type' => $media_type
        ];
    }

    return $out;
}