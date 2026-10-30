<?php
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$users = $conn->query(
    'SELECT id, name, email, role, created_at FROM users ORDER BY id DESC'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" dir="<?= page_direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= tr('إدارة المستخدمين | اختاري طريقك', 'Manage users | Choose Your Path') ?></title>
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
        <a href="../index.php"><?= tr('عرض الموقع', 'View site') ?></a>
        <a href="../logout.php"><?= tr('تسجيل الخروج', 'Log out') ?></a>
    </nav>
    <div class="language-switch">
        <a href="<?= e(language_url('ar')) ?>" lang="ar" aria-current="<?= page_language() === 'ar' ? 'true' : 'false' ?>">عربي</a>
        <a href="<?= e(language_url('en')) ?>" lang="en" aria-current="<?= page_language() === 'en' ? 'true' : 'false' ?>">EN</a>
    </div>
</header>
<main class="users-page">
    <p class="eyebrow"><?= tr('إدارة الحسابات', 'Account management') ?></p>
    <h1><?= tr('المستخدمون المسجلون', 'Registered users') ?></h1>
    <p><?= tr('إجمالي الحسابات:', 'Total accounts:') ?> <span class="users-count"><?= count($users) ?></span></p>
    <section class="users-box">
        <?php if (empty($users)): ?>
            <p><?= tr('لا توجد حسابات مسجلة حتى الآن.', 'No accounts have been registered yet.') ?></p>
        <?php else: ?>
            <table class="users-table">
                <caption><?= tr('قائمة المستخدمين المسجلين في المنصة', 'Users registered on the platform') ?></caption>
                <thead>
                    <tr>
                        <th><?= tr('رقم الحساب', 'Account ID') ?></th>
                        <th><?= tr('الاسم', 'Name') ?></th>
                        <th><?= tr('البريد الإلكتروني', 'Email address') ?></th>
                        <th><?= tr('الصلاحية', 'Role') ?></th>
                        <th><?= tr('تاريخ التسجيل', 'Registration date') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= e($user['id']) ?></td>
                            <td><?= e($user['name']) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td class="<?= $user['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                                <?= $user['role'] === 'admin' ? tr('مسؤولة', 'Admin') : tr('مستخدمة', 'User') ?>
                            </td>
                            <td><?= e($user['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>
<footer class="site-footer">
    <p>© <?= date('Y') ?> <?= tr('اختاري طريقك', 'Choose Your Path') ?></p>
</footer>
</body>
</html>
