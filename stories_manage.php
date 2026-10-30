<?php
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/content_media.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$newVideo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = tr('الطلب غير صالح. حدّثي الصفحة وحاولي مرة أخرى.', 'Invalid request. Refresh the page and try again.');
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add' || $action === 'update') {
                $fields = ['title', 'title_en', 'description', 'description_en'];
                $values = [];
                foreach ($fields as $field) {
                    $value = $_POST[$field] ?? '';
                    $values[$field] = is_string($value) ? trim($value) : '';
                }

                if (
                    $values['title'] === '' ||
                    $values['title_en'] === '' ||
                    $values['description'] === '' ||
                    $values['description_en'] === ''
                ) {
                    $error = tr(
                        'أدخلي العنوان والوصف بالعربية والإنجليزية.',
                        'Enter the title and description in Arabic and English.'
                    );
                } elseif (mb_strlen($values['title'], 'UTF-8') > 200 || mb_strlen($values['title_en'], 'UTF-8') > 200) {
                    $error = tr('يجب ألا يتجاوز العنوان 200 حرف.', 'Titles must not exceed 200 characters.');
                } else {
                    $storyId = null;
                    $existingStory = null;
                    if ($action === 'update') {
                        $storyId = filter_var($_POST['story_id'] ?? null, FILTER_VALIDATE_INT);
                        if (!$storyId || $storyId < 1) {
                            $error = tr('رقم الفيديو غير صالح.', 'Invalid video ID.');
                        } else {
                            $statement = $conn->prepare(
                                'SELECT video_path, video_mime FROM stories WHERE id = :id LIMIT 1'
                            );
                            $statement->execute([':id' => $storyId]);
                            $existingStory = $statement->fetch(PDO::FETCH_ASSOC);
                            if (!$existingStory) {
                                $error = tr('الفيديو غير موجود.', 'The video was not found.');
                            }
                        }
                    }

                    if ($error === '') {
                        $newVideo = upload_story_video();
                        if ($action === 'add' && $newVideo === null) {
                            $error = tr('اختاري فيديو لإضافته إلى Story.', 'Choose a video to add to Story.');
                        } elseif ($action === 'add') {
                            $statement = $conn->prepare(
                                'INSERT INTO stories
                                 (title, title_en, description, description_en, video_path, video_mime)
                                 VALUES (:title, :title_en, :description, :description_en, :video_path, :video_mime)'
                            );
                            $statement->execute([
                                ':title' => $values['title'],
                                ':title_en' => $values['title_en'],
                                ':description' => $values['description'],
                                ':description_en' => $values['description_en'],
                                ':video_path' => $newVideo['path'],
                                ':video_mime' => $newVideo['mime']
                            ]);
                            $newVideo = null;
                            $message = tr('تمت إضافة الفيديو إلى Story.', 'The video was added to Story.');
                        } else {
                            $video = $newVideo ?? [
                                'path' => $existingStory['video_path'],
                                'mime' => $existingStory['video_mime']
                            ];
                            $statement = $conn->prepare(
                                'UPDATE stories
                                 SET title = :title, title_en = :title_en,
                                     description = :description, description_en = :description_en,
                                     video_path = :video_path, video_mime = :video_mime
                                 WHERE id = :id'
                            );
                            $statement->execute([
                                ':title' => $values['title'],
                                ':title_en' => $values['title_en'],
                                ':description' => $values['description'],
                                ':description_en' => $values['description_en'],
                                ':video_path' => $video['path'],
                                ':video_mime' => $video['mime'],
                                ':id' => $storyId
                            ]);
                            if ($newVideo !== null) {
                                delete_content_media_files([[
                                    'path' => $existingStory['video_path'],
                                    'type' => 'video',
                                    'mime' => $existingStory['video_mime']
                                ]]);
                            }
                            $newVideo = null;
                            $message = $statement->rowCount() > 0
                                ? tr('تم تحديث فيديو Story.', 'The Story video was updated.')
                                : tr('لم يتم العثور على تغييرات لحفظها.', 'No changes were found to save.');
                        }
                    }
                }
            } elseif ($action === 'delete') {
                $storyId = filter_var($_POST['story_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$storyId || $storyId < 1) {
                    $error = tr('رقم الفيديو غير صالح.', 'Invalid video ID.');
                } else {
                    $statement = $conn->prepare(
                        'SELECT video_path, video_mime FROM stories WHERE id = :id LIMIT 1'
                    );
                    $statement->execute([':id' => $storyId]);
                    $video = $statement->fetch(PDO::FETCH_ASSOC);
                    $statement = $conn->prepare('DELETE FROM stories WHERE id = :id');
                    $statement->execute([':id' => $storyId]);
                    if ($statement->rowCount() > 0 && $video) {
                        delete_content_media_files([[
                            'path' => $video['video_path'],
                            'type' => 'video',
                            'mime' => $video['video_mime']
                        ]]);
                        $message = tr('تم حذف الفيديو.', 'The video was deleted.');
                    } else {
                        $error = tr('الفيديو غير موجود.', 'The video was not found.');
                    }
                }
            } else {
                $error = tr('الطلب غير معروف.', 'Unknown request.');
            }
        } catch (PDOException $exception) {
            if ($newVideo !== null) {
                delete_content_media_files([$newVideo]);
            }
            error_log('Story management failed: ' . $exception->getMessage());
            $error = tr('حدث خطأ أثناء حفظ الفيديو. حاولي مرة أخرى.', 'Could not save the video. Please try again.');
        } catch (RuntimeException $exception) {
            if ($newVideo !== null) {
                delete_content_media_files([$newVideo]);
            }
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($newVideo !== null) {
                delete_content_media_files([$newVideo]);
            }
            error_log('Story management failed: ' . $exception->getMessage());
            $error = tr('تعذر معالجة الفيديو. حاولي مرة أخرى.', 'Could not process the video. Please try again.');
        }
    }
}

$stories = $conn->query(
    'SELECT id, title, title_en, description, description_en, video_path, video_mime, created_at
     FROM stories
     ORDER BY created_at DESC, id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

function story_manage_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" dir="<?= page_direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Story | <?= tr('إدارة المنصة', 'Admin dashboard') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="../index.php">
        <img class="site-logo" src="../assets/logo.png" width="52" height="52" alt="<?= tr('شعار مبادرة اختاري طريقك', 'Choose Your Path initiative logo') ?>">
        <span><?= tr('اختاري طريقك', 'Choose Your Path') ?></span>
    </a>
    <nav class="navigation">
        <a href="dashboard.php"><?= tr('لوحة التحكم', 'Dashboard') ?></a>
        <a href="../story.php">Story</a>
        <a href="../logout.php"><?= tr('تسجيل الخروج', 'Log out') ?></a>
    </nav>
    <div class="language-switch">
        <a href="<?= story_manage_e(language_url('ar')) ?>" lang="ar" aria-current="<?= page_language() === 'ar' ? 'true' : 'false' ?>">عربي</a>
        <a href="<?= story_manage_e(language_url('en')) ?>" lang="en" aria-current="<?= page_language() === 'en' ? 'true' : 'false' ?>">EN</a>
    </div>
</header>
<main class="manage-box admin-page manage-page">
    <p class="eyebrow"><?= tr('فيديوهات المنصة', 'Platform videos') ?></p>
    <h1>Story</h1>
    <p class="manage-intro"><?= tr('أضيفي فيديوهات ملهمة إلى قسم Story. يمكنكِ استبدال الفيديو عند التعديل.', 'Add videos to the Story section. You can replace a video when editing.') ?></p>
    <?php if ($message !== ''): ?><p class="success" role="status"><?= story_manage_e($message) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= story_manage_e($error) ?></p><?php endif; ?>

    <section class="manage-form-panel">
        <h2><?= tr('إضافة فيديو إلى Story', 'Add a video to Story') ?></h2>
        <form method="POST" class="manage-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= story_manage_e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="add">
            <label for="story-title"><?= tr('العنوان بالعربية', 'Title in Arabic') ?></label>
            <input id="story-title" name="title" maxlength="200" required>
            <label for="story-title-en"><?= tr('العنوان بالإنجليزية', 'Title in English') ?></label>
            <input id="story-title-en" name="title_en" maxlength="200" dir="ltr" required>
            <label for="story-description"><?= tr('الوصف بالعربية', 'Description in Arabic') ?></label>
            <textarea id="story-description" name="description" rows="4" required></textarea>
            <label for="story-description-en"><?= tr('الوصف بالإنجليزية', 'Description in English') ?></label>
            <textarea id="story-description-en" name="description_en" rows="4" dir="ltr" required></textarea>
            <label for="story-video"><?= tr('اختاري فيديو (MP4 أو WebM أو MOV)', 'Choose a video (MP4, WebM or MOV)') ?></label>
            <input id="story-video" type="file" name="story_video[]" accept="video/mp4,video/webm,video/quicktime" required>
            <small class="text-muted"><?= tr('فيديو واحد بحد أقصى 100 ميجابايت.', 'One video, up to 100 MB.') ?></small>
            <button type="submit"><?= tr('إضافة إلى Story', 'Add to Story') ?></button>
        </form>
    </section>

    <div class="manage-list-heading">
        <h2><?= tr('فيديوهات Story الحالية', 'Current Story videos') ?></h2>
        <span class="content-count"><?= count($stories) ?> <?= tr('فيديو', 'videos') ?></span>
    </div>
    <?php if ($stories === []): ?>
        <p><?= tr('لا توجد فيديوهات بعد.', 'No videos yet.') ?></p>
    <?php else: ?>
        <?php foreach ($stories as $story): ?>
            <section class="story-admin-row">
                <h3><?= story_manage_e($story['title']) ?> <span lang="en" dir="ltr">/ <?= story_manage_e($story['title_en']) ?></span></h3>
                <video controls preload="metadata" src="../<?= story_manage_e($story['video_path']) ?>"></video>
                <form method="POST" class="manage-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= story_manage_e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="story_id" value="<?= (int) $story['id'] ?>">
                    <label><?= tr('العنوان بالعربية', 'Title in Arabic') ?></label>
                    <input name="title" maxlength="200" value="<?= story_manage_e($story['title']) ?>" required>
                    <label><?= tr('العنوان بالإنجليزية', 'Title in English') ?></label>
                    <input name="title_en" maxlength="200" value="<?= story_manage_e($story['title_en']) ?>" dir="ltr" required>
                    <label><?= tr('الوصف بالعربية', 'Description in Arabic') ?></label>
                    <textarea name="description" rows="3" required><?= story_manage_e($story['description']) ?></textarea>
                    <label><?= tr('الوصف بالإنجليزية', 'Description in English') ?></label>
                    <textarea name="description_en" rows="3" dir="ltr" required><?= story_manage_e($story['description_en']) ?></textarea>
                    <label><?= tr('استبدال الفيديو (اختياري)', 'Replace video (optional)') ?></label>
                    <input type="file" name="story_video[]" accept="video/mp4,video/webm,video/quicktime">
                    <button type="submit"><?= tr('حفظ التعديلات', 'Save changes') ?></button>
                </form>
                <form method="POST" onsubmit="return confirm(<?= htmlspecialchars(json_encode(tr('هل أنتِ متأكدة من حذف هذا الفيديو؟', 'Are you sure you want to delete this video?'), JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>);">
                    <input type="hidden" name="csrf_token" value="<?= story_manage_e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="story_id" value="<?= (int) $story['id'] ?>">
                    <button class="delete-button" type="submit"><?= tr('حذف الفيديو', 'Delete video') ?></button>
                </form>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <p><a href="dashboard.php"><?= tr('العودة إلى لوحة التحكم', 'Back to dashboard') ?></a></p>
</main>
</body>
</html>
