<?php
require_once __DIR__ . '/config/i18n.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$messageType = 'error';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameInput = $_POST['name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $name = is_string($nameInput) ? trim($nameInput) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';

    if (
        $name === '' ||
        strlen($name) > 100 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        !is_string($password) ||
        strlen($password) < 8
    ) {
        $message = tr(
            'أدخلي اسمًا لا يتجاوز 100 حرف، وبريدًا إلكترونيًا صحيحًا، وكلمة مرور من 8 أحرف على الأقل.',
            'Enter a name of up to 100 characters, a valid email address and a password of at least 8 characters.'
        );
    } else {
        try {
            $stmt = $conn->prepare(
                'INSERT INTO users (name, email, password)
                 VALUES (:name, :email, :password)'
            );
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT)
            ]);

            $message = tr(
                'تم إنشاء حسابك بنجاح! يمكنكِ تسجيل الدخول الآن.',
                'Your account was created successfully. You can now log in.'
            );
            $messageType = 'success';
            $name = '';
            $email = '';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $message = tr(
                    'هذا البريد الإلكتروني مسجل بالفعل.',
                    'This email address is already registered.'
                );
            } else {
                error_log('Account registration failed: ' . $e->getMessage());
                $message = tr(
                    'حدث خطأ أثناء التسجيل. حاولي مرة أخرى.',
                    'Something went wrong while creating your account. Please try again.'
                );
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" dir="<?= page_direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= tr('إنشاء حساب | اختاري طريقك', 'Create an account | Choose Your Path') ?></title>
    <link rel="stylesheet" href="assets/style.css?v=20261010-2237">
</head>
<body class="auth-page">
<main class="auth-card register-card">
    <div class="language-switch" aria-label="<?= tr('اختاري اللغة', 'Choose language') ?>">
        <a href="<?= htmlspecialchars(language_url('ar'), ENT_QUOTES, 'UTF-8') ?>"
           lang="ar"
           aria-current="<?= page_language() === 'ar' ? 'true' : 'false' ?>">عربي</a>
        <a href="<?= htmlspecialchars(language_url('en'), ENT_QUOTES, 'UTF-8') ?>"
           lang="en"
           aria-current="<?= page_language() === 'en' ? 'true' : 'false' ?>">EN</a>
    </div>

    <a class="auth-brand" href="index.php">
        <img
            class="site-logo"
            src="assets/logo.png"
            width="60"
            height="60"
            alt="<?= tr('شعار مبادرة اختاري طريقك', 'Choose Your Path initiative logo') ?>"
        >
        <span><?= tr('اختاري طريقك', 'Choose Your Path') ?></span>
    </a>

    <h1><?= tr('انضمي إلى مجتمعنا', 'Join our community') ?></h1>
    <p class="register-intro">
        <?= tr(
            'أنشئي حسابك وابدئي رحلتك نحو وعي وأمان رقمي أفضل.',
            'Create an account and start building digital confidence and safety.'
        ) ?>
    </p>

    <?php if ($message !== ''): ?>
        <p class="<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"
           role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <label for="name"><?= tr('الاسم', 'Name') ?></label>
        <input
            id="name"
            type="text"
            name="name"
            maxlength="100"
            autocomplete="name"
            value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="email"><?= tr('البريد الإلكتروني', 'Email address') ?></label>
        <input
            id="email"
            type="email"
            name="email"
            maxlength="150"
            autocomplete="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <label for="password">
            <?= tr('كلمة المرور (8 أحرف على الأقل)', 'Password (at least 8 characters)') ?>
        </label>
        <div class="password-field">
            <input
                id="password"
                type="password"
                name="password"
                minlength="8"
                autocomplete="new-password"
                required
            >
            <button
                class="password-toggle"
                type="button"
                data-password-toggle
                data-show-label="<?= tr('إظهار كلمة المرور', 'Show password') ?>"
                data-hide-label="<?= tr('إخفاء كلمة المرور', 'Hide password') ?>"
                aria-label="<?= tr('إظهار كلمة المرور', 'Show password') ?>"
                aria-pressed="false"
                aria-controls="password"
            >
                <svg class="password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg class="password-icon-hide" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                    <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.6 15.6 0 0 1-3.1 3.9M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"></path>
                </svg>
            </button>
        </div>

        <button type="submit"><?= tr('إنشاء حساب', 'Create account') ?></button>
    </form>

    <div class="register-links">
        <p>
            <?= tr('لديكِ حساب بالفعل؟', 'Already have an account?') ?>
            <a href="login.php"><?= tr('تسجيل الدخول', 'Log in') ?></a>
        </p>
        <a href="index.php"><?= tr('العودة إلى الرئيسية', 'Back to home') ?></a>
    </div>
</main>
<script src="assets/password-toggle.js?v=20261010-2237" defer></script>
</body>
</html>
