<?php

function upload_content_media(string $field = 'media_files'): array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return [];
    }

    $files = $_FILES[$field];
    $fileCount = count($files['name']);
    if ($fileCount > 10) {
        throw new RuntimeException(tr(
            'يمكن رفع 10 ملفات كحد أقصى في كل مرة.',
            'You can upload up to 10 files at a time.'
        ));
    }

    $allowedTypes = [
        'image/jpeg' => ['extension' => 'jpg', 'max_size' => 10 * 1024 * 1024],
        'image/png' => ['extension' => 'png', 'max_size' => 10 * 1024 * 1024],
        'image/gif' => ['extension' => 'gif', 'max_size' => 10 * 1024 * 1024],
        'image/webp' => ['extension' => 'webp', 'max_size' => 10 * 1024 * 1024],
        'image/avif' => ['extension' => 'avif', 'max_size' => 10 * 1024 * 1024],
        'video/mp4' => ['extension' => 'mp4', 'max_size' => 100 * 1024 * 1024],
        'video/webm' => ['extension' => 'webm', 'max_size' => 100 * 1024 * 1024],
        'video/quicktime' => ['extension' => 'mov', 'max_size' => 100 * 1024 * 1024]
    ];
    $uploadDirectory = dirname(__DIR__) . '/uploads/media';
    $storedFiles = [];

    try {
        for ($index = 0; $index < $fileCount; $index++) {
            $errorCode = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($errorCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new RuntimeException(tr(
                    'تعذر رفع أحد الملفات. تحققي من حجمه وإعدادات رفع الملفات في PHP.',
                    'A file could not be uploaded. Check its size and the PHP upload settings.'
                ));
            }

            $temporaryPath = $files['tmp_name'][$index] ?? '';
            $fileSize = $files['size'][$index] ?? 0;
            if (
                !is_string($temporaryPath) ||
                !is_uploaded_file($temporaryPath) ||
                !is_int($fileSize) ||
                $fileSize < 1
            ) {
                throw new RuntimeException(tr('أحد الملفات المرفوعة غير صالح.', 'One of the uploaded files is invalid.'));
            }

            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($temporaryPath);
            if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
                throw new RuntimeException(tr(
                    'نوع الملف غير مدعوم. المسموح صور JPG وPNG وGIF وWebP وAVIF وفيديو MP4 وWebM وMOV.',
                    'Unsupported file type. Allowed: JPG, PNG, GIF, WebP, AVIF, MP4, WebM and MOV.'
                ));
            }

            if ($fileSize > $allowedTypes[$mimeType]['max_size']) {
                $maximumMegabytes = strpos($mimeType, 'video/') === 0 ? 100 : 10;
                throw new RuntimeException(tr(
                    'حجم الملف يتجاوز الحد المسموح (' . $maximumMegabytes . ' ميجابايت).',
                    'The file exceeds the allowed size (' . $maximumMegabytes . ' MB).'
                ));
            }

            if (
                !is_dir($uploadDirectory) &&
                !mkdir($uploadDirectory, 0755, true) &&
                !is_dir($uploadDirectory)
            ) {
                throw new RuntimeException(tr(
                    'تعذر تجهيز مجلد حفظ المرفقات.',
                    'Could not prepare the media upload directory.'
                ));
            }

            $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType]['extension'];
            $destination = $uploadDirectory . '/' . $fileName;
            if (!move_uploaded_file($temporaryPath, $destination)) {
                throw new RuntimeException(tr('تعذر حفظ الملف المرفوع.', 'Could not save the uploaded file.'));
            }

            $storedFiles[] = [
                'path' => 'uploads/media/' . $fileName,
                'type' => strpos($mimeType, 'image/') === 0 ? 'image' : 'video',
                'mime' => $mimeType
            ];
        }
    } catch (Throwable $exception) {
        delete_content_media_files($storedFiles);
        throw $exception;
    }

    return $storedFiles;
}

function upload_story_video(): ?array
{
    $videos = upload_content_media('story_video');
    if ($videos === []) {
        return null;
    }

    if (count($videos) !== 1 || $videos[0]['type'] !== 'video') {
        delete_content_media_files($videos);
        throw new RuntimeException(tr(
            'ارفعي فيديو واحدًا فقط بصيغة MP4 أو WebM أو MOV.',
            'Upload exactly one video in MP4, WebM or MOV format.'
        ));
    }

    return $videos[0];
}

function decode_content_media($encodedMedia): array
{
    if (!is_string($encodedMedia) || $encodedMedia === '') {
        return [];
    }

    $media = json_decode($encodedMedia, true);
    if (!is_array($media)) {
        return [];
    }

    return array_values(array_filter($media, static function ($item): bool {
        return is_array($item)
            && isset($item['path'], $item['type'], $item['mime'])
            && is_string($item['path'])
            && preg_match('#^uploads/media/[a-f0-9]{32}\.(jpg|png|gif|webp|avif|mp4|webm|mov)$#', $item['path']) === 1
            && in_array($item['type'], ['image', 'video'], true)
            && is_string($item['mime']);
    }));
}

function render_content_media_carousel(array $media, string $title, string $carouselId): string
{
    if ($media === []) {
        return '';
    }

    $escape = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $slides = '';
    $slideCount = 0;
    foreach ($media as $index => $item) {
        $path = $item['path'] ?? '';
        $type = $item['type'] ?? '';
        if (
            !is_string($path) ||
            !is_string($type) ||
            preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            continue;
        }

        $urlParts = parse_url($path);
        if (
            $urlParts === false ||
            (isset($urlParts['scheme']) && !in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)) ||
            (isset($urlParts['host']) && !isset($urlParts['scheme'])) ||
            !in_array($type, ['image', 'video'], true)
        ) {
            continue;
        }

        $active = $index === 0;
        $slides .= '<div class="content-carousel-slide' . ($active ? ' is-active' : '') . '"'
            . ($active ? '' : ' hidden')
            . ' aria-hidden="' . ($active ? 'false' : 'true') . '">';
        if ($type === 'video') {
            $slides .= '<video controls preload="metadata" src="' . $escape($path)
                . '" aria-label="' . $escape($title) . '"></video>';
        } else {
            $slides .= '<img src="' . $escape($path) . '" alt="' . $escape($title)
                . '" loading="lazy">';
        }
        $slides .= '</div>';
        $slideCount++;
    }

    if ($slides === '') {
        return '';
    }

    $id = preg_replace('/[^A-Za-z0-9_-]/', '-', $carouselId);
    if (!is_string($id) || $id === '') {
        return '';
    }

    $markup = '<div class="content-carousel" id="' . $escape($id)
        . '" aria-label="' . $escape($title) . '">';
    $markup .= '<div class="content-carousel-track" aria-live="polite">' . $slides . '</div>';

    if ($slideCount > 1) {
        $markup .= '<button class="content-carousel-control content-carousel-prev" type="button"'
            . ' data-carousel-direction="-1" aria-label="'
            . $escape(page_language() === 'en' ? 'Previous media' : 'المرفق السابق') . '">'
            . '<span aria-hidden="true">‹</span></button>';
        $markup .= '<button class="content-carousel-control content-carousel-next" type="button"'
            . ' data-carousel-direction="1" aria-label="'
            . $escape(page_language() === 'en' ? 'Next media' : 'المرفق التالي') . '">'
            . '<span aria-hidden="true">›</span></button>';
        $markup .= '<p class="content-carousel-count" aria-live="polite"></p>';
    }

    return $markup . '</div>';
}

function delete_content_media_files(array $media): void
{
    $uploadDirectory = dirname(__DIR__) . '/uploads/media/';
    foreach ($media as $item) {
        if (!is_array($item) || !isset($item['path']) || !is_string($item['path'])) {
            continue;
        }

        if (
            preg_match('#^uploads/media/[a-f0-9]{32}\.(jpg|png|gif|webp|avif|mp4|webm|mov)$#', $item['path']) === 1
        ) {
            $filePath = $uploadDirectory . basename($item['path']);
            if (is_file($filePath) && !unlink($filePath)) {
                error_log('Could not remove content media file: ' . $filePath);
            }
        }
    }
}
