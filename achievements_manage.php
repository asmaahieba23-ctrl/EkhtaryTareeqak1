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
$pendingMedia = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = tr('الطلب غير صالح. حدّثي الصفحة وحاولي مرة أخرى.', 'Invalid request. Refresh the page and try again.');
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add') {
                $fields = ['title', 'title_en', 'description', 'description_en', 'achievement_date'];
                $values = [];
                foreach ($fields as $field) {
                    $value = $_POST[$field] ?? '';
                    $values[$field] = is_string($value) ? trim($value) : '';
                }

                if (
                    $values['title'] === '' || $values['title_en'] === '' ||
                    $values['description'] === '' || $values['description_en'] === ''
                ) {
                    $error = tr(
                        'أدخلي العنوان والوصف باللغتين العربية والإنجليزية.',
                        'Enter the title and description in both Arabic and English.'
                    );
                } elseif (
                    $values['achievement_date'] !== '' &&
                    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['achievement_date'])
                ) {
                    $error = tr('تاريخ الإنجاز غير صالح.', 'Invalid achievement date.');
                } else {
                    $pendingMedia = upload_content_media();
                    $stmt = $conn->prepare(
                        'INSERT INTO achievements
                         (title, title_en, description, description_en, achievement_date, media_json)
                         VALUES (:title, :title_en, :description, :description_en, :achievement_date, :media_json)'
                    );
                    $stmt->execute([
                        ':title' => $values['title'],
                        ':title_en' => $values['title_en'],
                        ':description' => $values['description'],
                        ':description_en' => $values['description_en'],
                        ':achievement_date' => $values['achievement_date'] !== ''
                            ? $values['achievement_date'] : null,
                        ':media_json' => $pendingMedia !== []
                            ? json_encode($pendingMedia, JSON_UNESCAPED_SLASHES)
                            : null
                    ]);
                    $pendingMedia = [];
                    $message = tr('تمت إضافة الإنجاز بنجاح.', 'The achievement was added successfully.');
                }
            } elseif ($action === 'update') {
                $id = filter_var($_POST['achievement_id'] ?? null, FILTER_VALIDATE_INT);
                $fields = ['title', 'title_en', 'description', 'description_en', 'achievement_date'];
                $values = [];
                foreach ($fields as $field) {
                    $value = $_POST[$field] ?? '';
                    $values[$field] = is_string($value) ? trim($value) : '';
                }

                if (!$id || $id < 1) {
                    $error = tr('رقم الإنجاز غير صالح.', 'Invalid achievement ID.');
                } elseif (
                    $values['title'] === '' || $values['title_en'] === '' ||
                    $values['description'] === '' || $values['description_en'] === ''
                ) {
                    $error = tr(
                        'أدخلي العنوان والوصف باللغتين العربية والإنجليزية.',
                        'Enter the title and description in both Arabic and English.'
                    );
                } elseif (
                    $values['achievement_date'] !== '' &&
                    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['achievement_date'])
                ) {
                    $error = tr('تاريخ الإنجاز غير صالح.', 'Invalid achievement date.');
                } else {
                    $pendingMedia = upload_content_media();
                    $stmt = $conn->prepare('SELECT media_json FROM achievements WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    $existingAchievement = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$existingAchievement) {
                        delete_content_media_files($pendingMedia);
                        $pendingMedia = [];
                        $error = tr('الإنجاز غير موجود.', 'The achievement was not found.');
                    } else {
                        $media = array_merge(
                            decode_content_media($existingAchievement['media_json'] ?? null),
                            $pendingMedia
                        );
                    $stmt = $conn->prepare(
                        'UPDATE achievements
                         SET title = :title, title_en = :title_en,
                             description = :description, description_en = :description_en,
                             achievement_date = :achievement_date, media_json = :media_json
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':title' => $values['title'],
                        ':title_en' => $values['title_en'],
                        ':description' => $values['description'],
                        ':description_en' => $values['description_en'],
                        ':achievement_date' => $values['achievement_date'] !== ''
                            ? $values['achievement_date'] : null,
                        ':media_json' => $media !== []
                            ? json_encode($media, JSON_UNESCAPED_SLASHES)
                            : null,
                        ':id' => $id
                    ]);
                    $pendingMedia = [];
                    $message = $stmt->rowCount() > 0
                        ? tr('تم تحديث الإنجاز.', 'The achievement was updated.')
                        : tr('لم يتم العثور على تغييرات لحفظها.', 'No changes were found to save.');
                    }
                }
            } elseif ($action === 'delete') {
                $id = filter_var($_POST['achievement_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id || $id < 1) {
                    $error = tr('رقم الإنجاز غير صالح.', 'Invalid achievement ID.');
                } else {
                    $stmt = $conn->prepare('SELECT media_json FROM achievements WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    $achievementMedia = decode_content_media($stmt->fetchColumn());
                    $stmt = $conn->prepare('DELETE FROM achievements WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    if ($stmt->rowCount() > 0) {
                        delete_content_media_files($achievementMedia);
                    }
                    $message = $stmt->rowCount() > 0
                        ? tr('تم حذف الإنجاز.', 'The achievement was deleted.')
                        : tr('الإنجاز غير موجود.', 'The achievement was not found.');
                }
            }
        } catch (PDOException $e) {
            delete_content_media_files($pendingMedia);
            error_log('Achievement management failed: ' . $e->getMessage());
            $error = tr('حدث خطأ أثناء حفظ البيانات. حاولي مرة أخرى.', 'Could not save the changes. Please try again.');
        } catch (RuntimeException $e) {
            delete_content_media_files($pendingMedia);
            $error = $e->getMessage();
        } catch (Throwable $e) {
            delete_content_media_files($pendingMedia);
            error_log('Achievement media management failed: ' . $e->getMessage());
            $error = tr('تعذر معالجة المرفقات. حاولي مرة أخرى.', 'Could not process the attachments. Please try again.');
        }
    }
}

$achievements = $conn->query(
    'SELECT id, title, title_en, description, description_en, image, media_json, achievement_date
     FROM achievements ORDER BY id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" dir="<?= page_direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= tr('إدارة الإنجازات | اختاري طريقك', 'Manage achievements | Choose Your Path') ?></title>
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
        <a href="../logout.php"><?= tr('تسجيل الخروج', 'Log out') ?></a>
    </nav>
    <div class="language-switch">
        <a href="<?= e(language_url('ar')) ?>" lang="ar" aria-current="<?= page_language() === 'ar' ? 'true' : 'false' ?>">عربي</a>
        <a href="<?= e(language_url('en')) ?>" lang="en" aria-current="<?= page_language() === 'en' ? 'true' : 'false' ?>">EN</a>
    </div>
</header>
<main class="manage-box admin-page manage-page">
    <p class="eyebrow"><?= tr('المحتوى ثنائي اللغة', 'Bilingual content') ?></p>
    <h1><?= tr('إدارة الإنجازات', 'Manage achievements') ?></h1>
    <p class="manage-intro"><?= tr('أبرزي إنجازات المبادرة وتكريماتها، وأضيفي وصفًا وتاريخًا لكل محطة مهمة.', 'Highlight initiative achievements and recognitions with a description and date for every milestone.') ?></p>
    <?php if ($message !== ''): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <section class="manage-form-panel">
    <h2><?= tr('إضافة إنجاز أو تكريم', 'Add an achievement or recognition') ?></h2>
    <form method="POST" class="manage-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="add">
        <label for="title"><?= tr('العنوان بالعربية', 'Title in Arabic') ?></label>
        <input id="title" name="title" maxlength="200" required>
        <label for="title_en"><?= tr('العنوان بالإنجليزية', 'Title in English') ?></label>
        <input id="title_en" name="title_en" maxlength="200" dir="ltr" required>
        <label for="description"><?= tr('الوصف بالعربية', 'Description in Arabic') ?></label>
        <textarea id="description" name="description" rows="5" required></textarea>
        <label for="description_en"><?= tr('الوصف بالإنجليزية', 'Description in English') ?></label>
        <textarea id="description_en" name="description_en" rows="5" dir="ltr" required></textarea>
        <label for="achievement_date"><?= tr('تاريخ الإنجاز', 'Achievement date') ?></label>
        <input id="achievement_date" type="date" name="achievement_date">
        <label for="new_media_files"><?= tr('المرفقات (صور وفيديوهات)', 'Attachments (images and videos)') ?></label>
        <input id="new_media_files" type="file" name="media_files[]" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,video/quicktime" multiple>
        <small class="text-muted"><?= tr('يمكن رفع 10 ملفات كحد أقصى؛ الصور حتى 10 ميجابايت والفيديو حتى 100 ميجابايت للملف.', 'Upload up to 10 files; images up to 10 MB and videos up to 100 MB each.') ?></small>
        <button type="submit"><?= tr('إضافة الإنجاز', 'Add achievement') ?></button>
    </form>
    </section>

    <div class="manage-list-heading">
        <h2><?= tr('الإنجازات والتكريمات الحالية', 'Current achievements and recognitions') ?></h2>
        <span class="content-count"><?= count($achievements) ?> <?= tr('إنجاز', 'achievements') ?></span>
    </div>
    <?php if (empty($achievements)): ?>
        <p><?= tr('لا توجد إنجازات حتى الآن.', 'No achievements yet.') ?></p>
    <?php else: ?>
        <?php foreach ($achievements as $achievement): ?>
            <section class="achievement-row">
                <h3><?= e($achievement['title']) ?> <span lang="en" dir="ltr">/ <?= e($achievement['title_en'] ?? '') ?></span></h3>
                <?php
                $achievementMedia = decode_content_media($achievement['media_json'] ?? null);
                if (!empty($achievement['image'])) {
                    array_unshift($achievementMedia, [
                        'path' => $achievement['image'],
                        'type' => 'image',
                        'mime' => 'image/jpeg'
                    ]);
                }
                ?>
                <?php if ($achievementMedia !== []): ?>
                    <div class="content-media-list">
                        <?php foreach ($achievementMedia as $media): ?>
                            <?php if ($media['type'] === 'video'): ?>
                                <video controls preload="metadata" src="../<?= e($media['path']) ?>"></video>
                            <?php else: ?>
                                <img src="../<?= e($media['path']) ?>" alt="<?= e($achievement['title']) ?>" loading="lazy">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="achievement_id" value="<?= (int) $achievement['id'] ?>">
                    <label><?= tr('العنوان بالعربية', 'Title in Arabic') ?></label>
                    <input name="title" maxlength="200" value="<?= e($achievement['title']) ?>" required>
                    <label><?= tr('العنوان بالإنجليزية', 'Title in English') ?></label>
                    <input name="title_en" maxlength="200" value="<?= e($achievement['title_en'] ?? '') ?>" dir="ltr" required>
                    <label><?= tr('الوصف بالعربية', 'Description in Arabic') ?></label>
                    <textarea name="description" rows="4" required><?= e($achievement['description']) ?></textarea>
                    <label><?= tr('الوصف بالإنجليزية', 'Description in English') ?></label>
                    <textarea name="description_en" rows="4" dir="ltr" required><?= e($achievement['description_en'] ?? '') ?></textarea>
                    <label><?= tr('تاريخ الإنجاز', 'Achievement date') ?></label>
                    <input type="date" name="achievement_date" value="<?= e($achievement['achievement_date'] ?? '') ?>">
                    <label><?= tr('إضافة مرفقات صور أو فيديوهات', 'Add image or video attachments') ?></label>
                    <input type="file" name="media_files[]" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,video/quicktime" multiple>
                    <small class="text-muted"><?= tr('تُضاف الملفات الجديدة إلى المرفقات الحالية.', 'New files are added to the existing attachments.') ?></small>
                    <button type="submit"><?= tr('حفظ التعديلات', 'Save changes') ?></button>
                </form>
                <form method="POST" onsubmit="return confirm(<?= htmlspecialchars(json_encode(tr('هل أنتِ متأكدة من حذف هذا الإنجاز؟', 'Are you sure you want to delete this achievement?'), JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>);">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="achievement_id" value="<?= (int) $achievement['id'] ?>">
                    <button class="delete-button" type="submit"><?= tr('حذف الإنجاز', 'Delete achievement') ?></button>
                </form>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <p><a href="dashboard.php"><?= tr('العودة إلى لوحة التحكم', 'Back to dashboard') ?></a></p>
</main>
</body>
</html>
