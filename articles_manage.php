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
                $fields = ['title', 'title_en', 'summary', 'summary_en', 'content', 'content_en'];
                $values = [];
                foreach ($fields as $field) {
                    $value = $_POST[$field] ?? '';
                    $values[$field] = is_string($value) ? trim($value) : '';
                }

                if (
                    $values['title'] === '' || $values['title_en'] === '' ||
                    $values['content'] === '' || $values['content_en'] === ''
                ) {
                    $error = tr(
                        'أدخلي العنوان والمحتوى باللغتين العربية والإنجليزية.',
                        'Enter the title and content in both Arabic and English.'
                    );
                } else {
                    $pendingMedia = upload_content_media();
                    $stmt = $conn->prepare(
                        'INSERT INTO articles
                         (title, title_en, summary, summary_en, content, content_en, media_json)
                         VALUES (:title, :title_en, :summary, :summary_en, :content, :content_en, :media_json)'
                    );
                    $stmt->execute([
                        ':title' => $values['title'],
                        ':title_en' => $values['title_en'],
                        ':summary' => $values['summary'],
                        ':summary_en' => $values['summary_en'],
                        ':content' => $values['content'],
                        ':content_en' => $values['content_en'],
                        ':media_json' => $pendingMedia !== []
                            ? json_encode($pendingMedia, JSON_UNESCAPED_SLASHES)
                            : null
                    ]);
                    $pendingMedia = [];
                    $message = tr('تمت إضافة المقال بنجاح.', 'The article was added successfully.');
                }
            } elseif ($action === 'update') {
                $id = filter_var($_POST['article_id'] ?? null, FILTER_VALIDATE_INT);
                $fields = ['title', 'title_en', 'summary', 'summary_en', 'content', 'content_en'];
                $values = [];
                foreach ($fields as $field) {
                    $value = $_POST[$field] ?? '';
                    $values[$field] = is_string($value) ? trim($value) : '';
                }

                if (!$id || $id < 1) {
                    $error = tr('رقم المقال غير صالح.', 'Invalid article ID.');
                } elseif (
                    $values['title'] === '' || $values['title_en'] === '' ||
                    $values['content'] === '' || $values['content_en'] === ''
                ) {
                    $error = tr(
                        'أدخلي العنوان والمحتوى باللغتين العربية والإنجليزية.',
                        'Enter the title and content in both Arabic and English.'
                    );
                } else {
                    $pendingMedia = upload_content_media();
                    $stmt = $conn->prepare('SELECT media_json FROM articles WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    $existingArticle = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$existingArticle) {
                        delete_content_media_files($pendingMedia);
                        $pendingMedia = [];
                        $error = tr('المقال غير موجود.', 'The article was not found.');
                    } else {
                        $media = array_merge(
                            decode_content_media($existingArticle['media_json'] ?? null),
                            $pendingMedia
                        );
                    $stmt = $conn->prepare(
                        'UPDATE articles
                         SET title = :title, title_en = :title_en,
                             summary = :summary, summary_en = :summary_en,
                             content = :content, content_en = :content_en,
                             media_json = :media_json
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':title' => $values['title'],
                        ':title_en' => $values['title_en'],
                        ':summary' => $values['summary'],
                        ':summary_en' => $values['summary_en'],
                        ':content' => $values['content'],
                        ':content_en' => $values['content_en'],
                        ':media_json' => $media !== []
                            ? json_encode($media, JSON_UNESCAPED_SLASHES)
                            : null,
                        ':id' => $id
                    ]);
                    $pendingMedia = [];
                    $message = $stmt->rowCount() > 0
                        ? tr('تم تحديث المقال.', 'The article was updated.')
                        : tr('لم يتم العثور على تغييرات لحفظها.', 'No changes were found to save.');
                    }
                }
            } elseif ($action === 'delete') {
                $id = filter_var($_POST['article_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id || $id < 1) {
                    $error = tr('رقم المقال غير صالح.', 'Invalid article ID.');
                } else {
                    $stmt = $conn->prepare('SELECT media_json FROM articles WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    $articleMedia = decode_content_media(
                        $stmt->fetchColumn()
                    );
                    $stmt = $conn->prepare('DELETE FROM articles WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    if ($stmt->rowCount() > 0) {
                        delete_content_media_files($articleMedia);
                    }
                    $message = $stmt->rowCount() > 0
                        ? tr('تم حذف المقال.', 'The article was deleted.')
                        : tr('المقال غير موجود.', 'The article was not found.');
                }
            }
        } catch (PDOException $e) {
            delete_content_media_files($pendingMedia);
            error_log('Article management failed: ' . $e->getMessage());
            $error = tr('حدث خطأ أثناء حفظ البيانات. حاولي مرة أخرى.', 'Could not save the changes. Please try again.');
        } catch (RuntimeException $e) {
            delete_content_media_files($pendingMedia);
            $error = $e->getMessage();
        } catch (Throwable $e) {
            delete_content_media_files($pendingMedia);
            error_log('Article media management failed: ' . $e->getMessage());
            $error = tr('تعذر معالجة المرفقات. حاولي مرة أخرى.', 'Could not process the attachments. Please try again.');
        }
    }
}

$articles = $conn->query(
    'SELECT id, title, title_en, summary, summary_en, content, content_en, image, media_json, created_at
     FROM articles ORDER BY id DESC'
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
    <title><?= tr('إدارة المقالات | اختاري طريقك', 'Manage articles | Choose Your Path') ?></title>
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
    <h1><?= tr('إدارة المقالات', 'Manage articles') ?></h1>
    <p class="manage-intro"><?= tr('أضيفي المقالات وحرّري عناوينها وملخصاتها ومحتواها بالعربية والإنجليزية.', 'Add articles and edit their titles, summaries and content in Arabic and English.') ?></p>
    <?php if ($message !== ''): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <section class="manage-form-panel">
    <h2><?= tr('إضافة مقال جديد', 'Add a new article') ?></h2>
    <form method="POST" class="manage-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="add">
        <label for="title"><?= tr('العنوان بالعربية', 'Title in Arabic') ?></label>
        <input id="title" name="title" maxlength="200" required>
        <label for="title_en"><?= tr('العنوان بالإنجليزية', 'Title in English') ?></label>
        <input id="title_en" name="title_en" maxlength="200" dir="ltr" required>
        <label for="summary"><?= tr('الملخص بالعربية (اختياري)', 'Summary in Arabic (optional)') ?></label>
        <textarea id="summary" name="summary" rows="3"></textarea>
        <label for="summary_en"><?= tr('الملخص بالإنجليزية (اختياري)', 'Summary in English (optional)') ?></label>
        <textarea id="summary_en" name="summary_en" rows="3" dir="ltr"></textarea>
        <label for="content"><?= tr('المحتوى بالعربية', 'Content in Arabic') ?></label>
        <textarea id="content" name="content" rows="7" required></textarea>
        <label for="content_en"><?= tr('المحتوى بالإنجليزية', 'Content in English') ?></label>
        <textarea id="content_en" name="content_en" rows="7" dir="ltr" required></textarea>
        <label for="new_media_files"><?= tr('المرفقات (صور وفيديوهات)', 'Attachments (images and videos)') ?></label>
        <input id="new_media_files" type="file" name="media_files[]" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,video/quicktime" multiple>
        <small class="text-muted"><?= tr('يمكن رفع 10 ملفات كحد أقصى؛ الصور حتى 10 ميجابايت والفيديو حتى 100 ميجابايت للملف.', 'Upload up to 10 files; images up to 10 MB and videos up to 100 MB each.') ?></small>
        <button type="submit"><?= tr('إضافة المقال', 'Add article') ?></button>
    </form>
    </section>

    <div class="manage-list-heading">
        <h2><?= tr('المقالات الحالية', 'Current articles') ?></h2>
        <span class="content-count"><?= count($articles) ?> <?= tr('مقال', 'articles') ?></span>
    </div>
    <?php if (empty($articles)): ?>
        <p><?= tr('لا توجد مقالات حتى الآن.', 'No articles yet.') ?></p>
    <?php else: ?>
        <?php foreach ($articles as $article): ?>
            <section class="article-row">
                <h3><?= e($article['title']) ?> <span lang="en" dir="ltr">/ <?= e($article['title_en'] ?? '') ?></span></h3>
                <?php
                $articleMedia = decode_content_media($article['media_json'] ?? null);
                if (!empty($article['image'])) {
                    array_unshift($articleMedia, [
                        'path' => $article['image'],
                        'type' => 'image',
                        'mime' => 'image/jpeg'
                    ]);
                }
                ?>
                <?php if ($articleMedia !== []): ?>
                    <div class="content-media-list">
                        <?php foreach ($articleMedia as $media): ?>
                            <?php if ($media['type'] === 'video'): ?>
                                <video controls preload="metadata" src="../<?= e($media['path']) ?>"></video>
                            <?php else: ?>
                                <img src="../<?= e($media['path']) ?>" alt="<?= e($article['title']) ?>" loading="lazy">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
                    <label><?= tr('العنوان بالعربية', 'Title in Arabic') ?></label>
                    <input name="title" maxlength="200" value="<?= e($article['title']) ?>" required>
                    <label><?= tr('العنوان بالإنجليزية', 'Title in English') ?></label>
                    <input name="title_en" maxlength="200" value="<?= e($article['title_en'] ?? '') ?>" dir="ltr" required>
                    <label><?= tr('الملخص بالعربية', 'Summary in Arabic') ?></label>
                    <textarea name="summary" rows="2"><?= e($article['summary'] ?? '') ?></textarea>
                    <label><?= tr('الملخص بالإنجليزية', 'Summary in English') ?></label>
                    <textarea name="summary_en" rows="2" dir="ltr"><?= e($article['summary_en'] ?? '') ?></textarea>
                    <label><?= tr('المحتوى بالعربية', 'Content in Arabic') ?></label>
                    <textarea name="content" rows="5" required><?= e($article['content'] ?? '') ?></textarea>
                    <label><?= tr('المحتوى بالإنجليزية', 'Content in English') ?></label>
                    <textarea name="content_en" rows="5" dir="ltr" required><?= e($article['content_en'] ?? '') ?></textarea>
                    <label><?= tr('إضافة مرفقات صور أو فيديوهات', 'Add image or video attachments') ?></label>
                    <input type="file" name="media_files[]" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,video/quicktime" multiple>
                    <small class="text-muted"><?= tr('تُضاف الملفات الجديدة إلى المرفقات الحالية.', 'New files are added to the existing attachments.') ?></small>
                    <button type="submit"><?= tr('حفظ التعديلات', 'Save changes') ?></button>
                </form>
                <form method="POST" onsubmit="return confirm(<?= htmlspecialchars(json_encode(tr('هل أنتِ متأكدة من حذف هذا المقال؟', 'Are you sure you want to delete this article?'), JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>);">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
                    <button class="delete-button" type="submit"><?= tr('حذف المقال', 'Delete article') ?></button>
                </form>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <p><a href="dashboard.php"><?= tr('العودة إلى لوحة التحكم', 'Back to dashboard') ?></a></p>
</main>
</body>
</html>
