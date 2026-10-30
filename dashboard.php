<?php
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$userCount = (int) $conn->query('SELECT COUNT(*) FROM users')->fetchColumn();
$articleCount = (int) $conn->query('SELECT COUNT(*) FROM articles')->fetchColumn();
$achievementCount = (int) $conn->query('SELECT COUNT(*) FROM achievements')->fetchColumn();
$storyCount = (int) $conn->query('SELECT COUNT(*) FROM stories')->fetchColumn();
$analytics = null;
$analyticsError = '';
try {
    $analytics = $conn->query(
        "SELECT
            COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) AS unique_visitors,
            COALESCE(SUM(event_type = 'page_view'), 0) AS page_views,
            COUNT(DISTINCT CASE WHEN event_type = 'assistant_message' THEN visitor_hash END) AS assistant_users,
            COALESCE(SUM(event_type = 'assistant_message'), 0) AS assistant_messages,
            COUNT(DISTINCT CASE
                WHEN event_type IN ('stayed_30_sec', 'scrolled_to_end')
                    AND page_path LIKE '/articles.php#article-%'
                THEN visitor_hash END
            ) AS article_readers,
            COALESCE(SUM(
                event_type = 'stayed_30_sec'
                AND page_path LIKE '/articles.php#article-%'
            ), 0) AS articles_stayed_30_sec,
            COALESCE(SUM(
                event_type = 'scrolled_to_end'
                AND page_path LIKE '/articles.php#article-%'
            ), 0) AS articles_scrolled_to_end,
            COUNT(DISTINCT CASE
                WHEN event_type IN ('stayed_30_sec', 'scrolled_to_end')
                    AND page_path LIKE '/achievements.php#achievement-%'
                THEN visitor_hash END
            ) AS achievement_readers,
            COALESCE(SUM(
                event_type = 'stayed_30_sec'
                AND page_path LIKE '/achievements.php#achievement-%'
            ), 0) AS achievements_stayed_30_sec,
            COALESCE(SUM(
                event_type = 'scrolled_to_end'
                AND page_path LIKE '/achievements.php#achievement-%'
            ), 0) AS achievements_scrolled_to_end
         FROM site_analytics_events"
    )->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin analytics could not be loaded: ' . $e->getMessage());
    $analyticsError = tr(
        'تعذر تحميل الإحصاءات. تأكدي من تنفيذ ترحيل إحصاءات الزيارات.',
        'Analytics are unavailable. Make sure the visitor analytics migration has been applied.'
    );
}
$adminName = htmlspecialchars($_SESSION['user_name'] ?? tr('المسؤولة', 'Admin'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" dir="<?= page_direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= tr('لوحة الإدارة | اختاري طريقك', 'Admin dashboard | Choose Your Path') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="../index.php">
        <img class="site-logo" src="../assets/logo.png" width="52" height="52" alt="<?= tr('شعار مبادرة اختاري طريقك', 'Choose Your Path initiative logo') ?>">
        <span><?= tr('اختاري طريقك', 'Choose Your Path') ?></span>
    </a>
    <nav class="navigation">
        <a href="../index.php"><?= tr('عرض الموقع', 'View site') ?></a>
        <a href="../logout.php"><?= tr('تسجيل الخروج', 'Log out') ?></a>
    </nav>
    <div class="language-switch">
        <a href="<?= htmlspecialchars(language_url('ar'), ENT_QUOTES, 'UTF-8') ?>" lang="ar" aria-current="<?= page_language() === 'ar' ? 'true' : 'false' ?>">عربي</a>
        <a href="<?= htmlspecialchars(language_url('en'), ENT_QUOTES, 'UTF-8') ?>" lang="en" aria-current="<?= page_language() === 'en' ? 'true' : 'false' ?>">EN</a>
    </div>
</header>
<main class="admin-page">
    <section class="admin-welcome">
        <span class="admin-welcome-mark" aria-hidden="true">✦</span>
        <p class="eyebrow"><?= tr('لوحة الإدارة', 'Admin dashboard') ?></p>
        <h1><?= tr('أهلًا بكِ يا ', 'Welcome, ') ?><?= $adminName ?></h1>
        <p><?= tr('تابعي محتوى منصة اختاري طريقك وأديري موارده من مكان واحد.', 'Review and manage Choose Your Path content and resources in one place.') ?></p>
    </section>
    <section class="admin-stats">
        <?php if ($analytics !== null): ?>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">♙</span>
            <p><?= tr('الزائرات والزوار', 'Unique visitors') ?></p>
            <h2><?= (int) $analytics['unique_visitors'] ?></h2>
            <small><?= tr('أشخاص مميزون عبر متصفحهم، دون جمع البريد الإلكتروني أو عنوان IP.', 'Distinct browser visitors; no email address or IP address is collected.') ?></small>
        </article>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">◉</span>
            <p><?= tr('مشاهدات الصفحات', 'Page views') ?></p>
            <h2><?= (int) $analytics['page_views'] ?></h2>
        </article>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">💬</span>
            <p><?= tr('مستخدمات ومستخدمو المساعد', 'Assistant users') ?></p>
            <h2><?= (int) $analytics['assistant_users'] ?></h2>
            <small><?= (int) $analytics['assistant_messages'] ?> <?= tr('رسالة مرسلة', 'messages sent') ?></small>
        </article>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">▤</span>
            <p><?= tr('تفاعل مع المقالات', 'Article engagement') ?></p>
            <h2><?= (int) $analytics['article_readers'] ?></h2>
            <small>
                <?= (int) $analytics['articles_stayed_30_sec'] ?> <?= tr('بقاء 30 ثانية', 'stayed 30 seconds') ?>
                · <?= (int) $analytics['articles_scrolled_to_end'] ?> <?= tr('وصول لنهاية الصفحة', 'reached page end') ?>
            </small>
        </article>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">✦</span>
            <p><?= tr('تفاعل مع الإنجازات', 'Achievement engagement') ?></p>
            <h2><?= (int) $analytics['achievement_readers'] ?></h2>
            <small>
                <?= (int) $analytics['achievements_stayed_30_sec'] ?> <?= tr('بقاء 30 ثانية', 'stayed 30 seconds') ?>
                · <?= (int) $analytics['achievements_scrolled_to_end'] ?> <?= tr('وصول لنهاية الصفحة', 'reached page end') ?>
            </small>
        </article>
        <?php endif; ?>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">♧</span>
            <p><?= tr('المستخدمون المسجلون', 'Registered users') ?></p>
            <h2><?= $userCount ?></h2>
            <a href="user_manage.php"><?= tr('استعراض الحسابات', 'Browse accounts') ?></a>
        </article>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">▤</span>
            <p><?= tr('المقالات', 'Articles') ?></p>
            <h2><?= $articleCount ?></h2>
            <a href="articles_manage.php"><?= tr('إضافة وإدارة المقالات', 'Add and manage articles') ?></a>
        </article>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">✦</span>
            <p><?= tr('الإنجازات', 'Achievements') ?></p>
            <h2><?= $achievementCount ?></h2>
            <a href="achievements_manage.php"><?= tr('إضافة وإدارة الإنجازات', 'Add and manage achievements') ?></a>
        </article>
        <article class="stat-card">
            <span class="stat-icon" aria-hidden="true">▶</span>
            <p>Story</p>
            <h2><?= $storyCount ?></h2>
            <a href="stories_manage.php"><?= tr('إضافة وإدارة قصص Story', 'Add and manage Story videos') ?></a>
        </article>
    </section>
    <?php if ($analyticsError !== ''): ?>
        <p class="error" role="alert"><?= htmlspecialchars($analyticsError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <section class="admin-management">
        <p class="eyebrow"><?= tr('مساحة العمل', 'Workspace') ?></p>
        <h2><?= tr('إدارة المنصة', 'Manage the platform') ?></h2>
        <div class="admin-links">
            <a class="admin-action-card" href="articles_manage.php">
                <span aria-hidden="true">▤</span>
                <strong><?= tr('إضافة وإدارة المقالات', 'Add and manage articles') ?></strong>
                <small><?= tr('أضيفي مقالًا جديدًا أو عدّلي المقالات الحالية ومرفقاتها.', 'Add a new article or edit existing articles and attachments.') ?></small>
            </a>
            <a class="admin-action-card" href="achievements_manage.php">
                <span aria-hidden="true">✦</span>
                <strong><?= tr('إضافة وإدارة الإنجازات والتكريمات', 'Add and manage achievements and recognitions') ?></strong>
                <small><?= tr('أضيفي إنجازًا أو تكريمًا جديدًا أو حدّثي محتوى المبادرة.', 'Add a new achievement or recognition, or update initiative content.') ?></small>
            </a>
            <a class="admin-action-card" href="stories_manage.php">
                <span aria-hidden="true">▶</span>
                <strong><?= tr('إضافة وإدارة قصص Story', 'Add and manage Story videos') ?></strong>
                <small><?= tr('أضيفي قصة فيديو جديدة أو عدّلي القصص الحالية واحذفيها.', 'Add a new video story, or edit and remove existing stories.') ?></small>
            </a>
            <a class="admin-action-card" href="user_manage.php">
                <span aria-hidden="true">♧</span>
                <strong><?= tr('إدارة المستخدمين', 'Manage users') ?></strong>
                <small><?= tr('راجعي الحسابات المسجلة في المنصة.', 'Review accounts registered on the platform.') ?></small>
            </a>
        </div>
    </section>
</main>
<footer class="site-footer">
    <p>© <?= date('Y') ?> <?= tr('اختاري طريقك', 'Choose Your Path') ?></p>
</footer>
</body>
</html>
