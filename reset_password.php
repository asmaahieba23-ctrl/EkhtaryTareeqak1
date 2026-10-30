<?php
require_once __DIR__ . '/config/i18n.php';
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
    $token = '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $token !== '') {
    try {
        $stmt = $conn->prepare(
            'SELECT id
             FROM password_resets
             WHERE token_hash = :token_hash
               AND expires_at > NOW()
               AND used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':token_hash' => hash('sha256', $token)]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $token = '';
            $message = tr(
                'رابط الاستعادة غير صالح أو انتهت صلاحيته. اطلبي رابطًا جديدًا.',
                'This reset link is invalid or expired. Request a new link.'
            );
        }
    } catch (PDOException $e) {
        error_log('Password reset link validation failed: ' . $e->getMessage());
        $token = '';
        $message = tr(
            'تعذر التحقق من رابط الاستعادة الآن. حاولي مرة أخرى لاحقًا.',
            'We could not verify this reset link right now. Please try again later.'
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $message = tr(
            'انتهت صلاحية الطلب. حدّثي الصفحة وحاولي مرة أخرى.',
            'This request has expired. Refresh the page and try again.'
        );
    } elseif ($token === '') {
        $message = tr('رابط الاستعادة غير صالح.', 'This reset link is invalid.');
    } elseif (!is_string($password) || !is_string($confirmPassword)) {
        $message = tr('أدخلي كلمة مرور صحيحة.', 'Enter a valid password.');
    } elseif (strlen($password) < 8) {
        $message = tr(
            'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'Your password must be at least 8 characters long.'
        );
    } elseif ($password !== $confirmPassword) {
        $message = tr('كلمتا المرور غير متطابقتين.', 'The passwords do not match.');
    } else {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare(
                'SELECT id, user_id
                 FROM password_resets
                 WHERE token_hash = :token_hash
                   AND expires_at > NOW()
                   AND used_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([':token_hash' => hash('sha256', $token)]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                $conn->rollBack();
                $message = tr(
                    'الرابط غير صالح أو انتهت صلاحيته أو استُخدم من قبل.',
                    'This link is invalid, expired or has already been used.'
                );
            } else {
                $stmt = $conn->prepare(
                    'UPDATE users SET password = :password WHERE id = :user_id'
                );
                $stmt->execute([
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':user_id' => $reset['user_id']
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Password reset did not update exactly one account.');
                }

                $stmt = $conn->prepare(
                    'UPDATE password_resets
                     SET used_at = NOW()
                     WHERE user_id = :user_id AND used_at IS NULL'
                );
                $stmt->execute([':user_id' => $reset['user_id']]);

                $conn->commit();
                $_SESSION['password_reset_success'] = true;
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Password reset failed: ' . $e->getMessage());
            $message = tr(
                'حدث خطأ أثناء تغيير كلمة المرور. حاولي مرة أخرى.',
                'Something went wrong while changing your password. Please try again.'
            );
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Password reset failed: ' . $e->getMessage());
            $message = tr(
                'حدث خطأ أثناء تغيير كلمة المرور. حاولي مرة أخرى.',
                'Something went wrong while changing your password. Please try again.'
            );
        }
    }
} elseif ($token === '') {
    $message = tr('رابط الاستعادة غير صالح.', 'This reset link is invalid.');
}
?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" dir="<?= page_direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= tr('تعيين كلمة مرور جديدة | اختاري طريقك', 'Set a new password | Choose Your Path') ?></title>
    <link rel="stylesheet" href="assets/style.css?v=20261010-2237">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="language-switch">
        <a href="<?= htmlspecialchars(language_url('ar'), ENT_QUOTES, 'UTF-8') ?>" lang="ar" aria-current="<?= page_language() === 'ar' ? 'true' : 'false' ?>">عربي</a>
        <a href="<?= htmlspecialchars(language_url('en'), ENT_QUOTES, 'UTF-8') ?>" lang="en" aria-current="<?= page_language() === 'en' ? 'true' : 'false' ?>">EN</a>
    </div>
    <a class="auth-brand" href="index.php">
        <img class="site-logo" src="assets/logo.png" alt="<?= tr('شعار مبادرة اختاري طريقك', 'Choose Your Path initiative logo') ?>">
        <span><?= tr('اختاري طريقك', 'Choose Your Path') ?></span>
    </a>
    <h1><?= tr('كلمة مرور جديدة', 'Create a new password') ?></h1>

    <?php if ($message !== ''): ?>
        <p class="error" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if ($token !== ''): ?>
        <form method="POST" action="reset.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <label for="password"><?= tr('كلمة المرور الجديدة', 'New password') ?></label>
            <div class="password-field">
                <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
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
            <label for="confirm_password"><?= tr('تأكيد كلمة المرور', 'Confirm password') ?></label>
            <div class="password-field">
                <input id="confirm_password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                <button
                    class="password-toggle"
                    type="button"
                    data-password-toggle
                    data-show-label="<?= tr('إظهار كلمة المرور', 'Show password') ?>"
                    data-hide-label="<?= tr('إخفاء كلمة المرور', 'Hide password') ?>"
                    aria-label="<?= tr('إظهار كلمة المرور', 'Show password') ?>"
                    aria-pressed="false"
                    aria-controls="confirm_password"
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
            <button type="submit"><?= tr('حفظ كلمة المرور الجديدة', 'Save new password') ?></button>
        </form>
    <?php endif; ?>

    <a href="login.php"><?= tr('الانتقال إلى تسجيل الدخول', 'Go to log in') ?></a>
</main>
<script src="assets/password-toggle.js?v=20261010-2237" defer></script>
</body>
</html>
