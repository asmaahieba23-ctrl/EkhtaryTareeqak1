<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Use Composer when installed; otherwise load the PHPMailer files in the project.
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. إنشاء كائن (Object) من المكتبة
$mail = new PHPMailer(true);

try {
    $senderEmail = 'sdh41759@gmail.com';
    $appPassword = getenv('SMTP_PASSWORD');
    if (!is_string($appPassword) || trim($appPassword) === '') {
        throw new RuntimeException('Set the Gmail app password in the SMTP_PASSWORD environment variable.');
    }

    // 3. إعدادات السيرفر (Server Settings)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $senderEmail;
    $mail->Password   = preg_replace('/\s+/', '', trim($appPassword));
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = PHPMailer::CHARSET_UTF8;

    // 4. أطراف البريد (Recipients)
    $mail->setFrom($senderEmail, 'مبادرة اختاري طريقك');
    $mail->addAddress($senderEmail);

    // 5. محتوى الرسالة (Content)
    $mail->isHTML(true);
    $mail->Subject = 'اختاري طريقك - اختبار إرسال البريد';
    $mail->Body    = '<div dir="rtl"><h1>أهلًا بكِ!</h1><p>هذه رسالة تجريبية من مبادرة اختاري طريقك.</p></div>';
    $mail->AltBody = 'هذه رسالة تجريبية من مبادرة اختاري طريقك.';

    // 6. أمر الإرسال الفعلي
    $mail->send();
    echo 'تم إرسال الرسالة بنجاح! 🎉';
} catch (Exception $e) {
    echo "عذراً، فشل إرسال الرسالة. خطأ الكومبيلر: {$mail->ErrorInfo}";
} catch (RuntimeException $e) {
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
