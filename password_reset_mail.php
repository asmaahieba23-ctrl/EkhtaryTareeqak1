<?php

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    $phpMailerSource = __DIR__ . '/../PHPMailer/src/';
    foreach (['Exception.php', 'SMTP.php', 'PHPMailer.php'] as $phpMailerFile) {
        $sourceFile = $phpMailerSource . $phpMailerFile;
        if (is_file($sourceFile)) {
            require_once $sourceFile;
        }
    }
}

function password_reset_mail_settings(): array
{
    $username = getenv('SMTP_USERNAME');
    $password = getenv('SMTP_PASSWORD');
    $host = getenv('SMTP_HOST');
    $port = getenv('SMTP_PORT');
    $fromAddress = getenv('MAIL_FROM_ADDRESS');
    $defaultSender = 'asmaahieba23@gmail.com';

    return [
        'username' => is_string($username) && trim($username) !== ''
            ? trim($username)
            : $defaultSender,
        'password' => is_string($password)
            ? (preg_replace('/\s+/', '', trim($password)) ?? '')
            : '',
        'host' => is_string($host) && trim($host) !== '' ? trim($host) : 'smtp.gmail.com',
        'port' => is_string($port) && ctype_digit($port) ? (int) $port : 587,
        'from' => is_string($fromAddress) && trim($fromAddress) !== ''
            ? trim($fromAddress)
            : (is_string($username) && trim($username) !== '' ? trim($username) : $defaultSender)
    ];
}

function password_reset_base_url(): ?string
{
    $configuredUrl = getenv('APP_BASE_URL');
    if (is_string($configuredUrl) && trim($configuredUrl) !== '') {
        $baseUrl = rtrim(trim($configuredUrl), '/');
    } else {
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        if (
            !is_string($serverName) ||
            !in_array(strtolower($serverName), ['localhost', '127.0.0.1', '::1'], true) ||
            !is_string($scriptName)
        ) {
            return null;
        }

        $directory = str_replace('\\', '/', dirname($scriptName));
        $baseUrl = 'http://' . strtolower($serverName)
            . ($directory === '/' || $directory === '.' ? '' : '/' . trim($directory, '/'));
    }

    $parts = parse_url($baseUrl);
    if (
        $parts === false ||
        !isset($parts['scheme'], $parts['host']) ||
        !in_array(strtolower($parts['scheme']), ['http', 'https'], true) ||
        array_key_exists('user', $parts) ||
        array_key_exists('pass', $parts) ||
        array_key_exists('query', $parts) ||
        array_key_exists('fragment', $parts) ||
        preg_match('/[\r\n]/', $baseUrl) === 1
    ) {
        return null;
    }

    return $baseUrl;
}

function password_reset_mail_configuration_error(): ?string
{
    $settings = password_reset_mail_settings();

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return 'PHPMailer is not installed. Run composer install.';
    }

    if (password_reset_base_url() === null) {
        return 'APP_BASE_URL is missing or invalid.';
    }

    if (
        $settings['username'] === '' ||
        filter_var($settings['username'], FILTER_VALIDATE_EMAIL) === false
    ) {
        return 'SMTP_USERNAME is missing or invalid.';
    }

    if ($settings['password'] === '') {
        return 'SMTP_PASSWORD is missing.';
    }

    if (
        $settings['host'] === '' ||
        preg_match('/[\r\n]/', $settings['host']) === 1 ||
        !in_array($settings['port'], [465, 587], true)
    ) {
        return 'SMTP_HOST or SMTP_PORT is invalid.';
    }

    if (filter_var($settings['from'], FILTER_VALIDATE_EMAIL) === false) {
        return 'MAIL_FROM_ADDRESS is invalid.';
    }

    if (strcasecmp($settings['from'], $settings['username']) !== 0) {
        return 'MAIL_FROM_ADDRESS must match SMTP_USERNAME for Gmail SMTP.';
    }

    return null;
}

function password_reset_mail_setup_message(string $error, string $language): string
{
    if (strpos($error, 'PHPMailer is not installed') !== false) {
        return $language === 'en'
            ? 'Email recovery is not active yet. The site administrator must install the project dependencies by running "composer install" in the project folder.'
            : 'استعادة كلمة المرور غير مفعّلة بعد؛ يجب على إدارة الموقع تثبيت المكتبات بتشغيل الأمر composer install داخل مجلد المشروع.';
    }

    if (
        strpos($error, 'SMTP_USERNAME') !== false ||
        strpos($error, 'SMTP_PASSWORD') !== false ||
        strpos($error, 'MAIL_FROM_ADDRESS') !== false
    ) {
        return $language === 'en'
            ? 'Email recovery needs the Gmail sender address and a Gmail App Password to be configured by the site administrator.'
            : 'تحتاج خدمة الاستعادة إلى إعداد بريد Gmail المُرسل وكلمة مرور تطبيق Gmail لدى إدارة الموقع.';
    }

    if (strpos($error, 'APP_BASE_URL') !== false) {
        return $language === 'en'
            ? 'The password reset link address is not configured correctly. Please contact the site administrator.'
            : 'عنوان رابط إعادة تعيين كلمة المرور غير مضبوط. يُرجى التواصل مع إدارة الموقع.';
    }

    return $language === 'en'
        ? 'Email recovery is not configured correctly. Please contact the site administrator.'
        : 'إعداد خدمة إرسال البريد غير مكتمل. يُرجى التواصل مع إدارة الموقع.';
}

function password_reset_url(string $token, string $language): string
{
    $baseUrl = password_reset_base_url();
    if ($baseUrl === null) {
        throw new RuntimeException('APP_BASE_URL is invalid for password recovery.');
    }

    return $baseUrl . '/reset.php?' . http_build_query([
        'token' => $token,
        'lang' => $language
    ], '', '&', PHP_QUERY_RFC3986);
}

function send_password_reset_email(string $email, string $resetUrl, string $language): bool
{
    $configurationError = password_reset_mail_configuration_error();
    if ($configurationError !== null) {
        error_log('Password recovery email is not configured: ' . $configurationError);
        return false;
    }

    $settings = password_reset_mail_settings();
    $isEnglish = $language === 'en';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $settings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['username'];
        $mail->Password = $settings['password'];
        $mail->SMTPSecure = $settings['port'] === 465
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $settings['port'];
        $mail->Timeout = 15;
        $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->setFrom(
            $settings['from'],
            $isEnglish ? 'Choose Your Path' : 'مبادرة اختاري طريقك'
        );
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = $isEnglish
            ? 'Choose Your Path - Password reset'
            : 'إعادة تعيين كلمة المرور - اختاري طريقك';

        $escapedResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($isEnglish) {
            $mail->Body = '<div style="font-family:Arial,sans-serif;line-height:1.8">'
                . '<h2>Hello,</h2>'
                . '<p>We received a request to reset the password for your Choose Your Path account.</p>'
                . '<p>This secure link expires in 15 minutes and can only be used once.</p>'
                . '<p><a href="' . $escapedResetUrl . '">Choose a new password</a></p>'
                . '<p>If you did not request this change, you can ignore this email.</p>'
                . '</div>';
            $mail->AltBody = "Use this secure link to choose a new password. "
                . "It expires in 15 minutes and can only be used once:\n" . $resetUrl;
        } else {
            $mail->Body = '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;line-height:1.9;text-align:right">'
                . '<h2>أهلًا بكِ،</h2>'
                . '<p>تلقينا طلبًا لإعادة تعيين كلمة المرور لحسابكِ في مبادرة اختاري طريقك.</p>'
                . '<p>الرابط الآمن صالح لمدة 15 دقيقة ويمكن استخدامه مرة واحدة فقط.</p>'
                . '<p><a href="' . $escapedResetUrl . '" style="display:inline-block;padding:10px 18px;border-radius:8px;background:#68418b;color:#fff;text-decoration:none">اختيار كلمة مرور جديدة</a></p>'
                . '<p>إذا لم تطلبي إعادة التعيين، يمكنكِ تجاهل هذه الرسالة.</p>'
                . '</div>';
            $mail->AltBody = "استخدمي الرابط الآمن لاختيار كلمة مرور جديدة. "
                . "تنتهي صلاحيته خلال 15 دقيقة ويمكن استخدامه مرة واحدة فقط:\n" . $resetUrl;
        }

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('Password recovery email delivery failed: ' . $mail->ErrorInfo);
        return false;
    }
}
