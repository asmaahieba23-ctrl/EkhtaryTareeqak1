<?php
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['chat_csrf'])) {
    $_SESSION['chat_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['chat_csrf'], $token)) {
        http_response_code(403);
        echo json_encode([
            'reply' => tr(
                'انتهت صلاحية الطلب. حدّثي الصفحة وحاولي مرة أخرى.',
                'This request has expired. Refresh the page and try again.'
            )
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $message = $_POST['message'] ?? '';
    if (!is_string($message) || trim($message) === '') {
        echo json_encode([
            'reply' => tr('اكتبي سؤالك الأول، وأنا هساعدك.', 'Type your question and I will do my best to help.')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    record_site_analytics($conn, 'assistant_message');

    $text = mb_strtolower(trim($message), 'UTF-8');
    $answers = [
        [
            'keys' => ['تحرير محضر', 'محضر', 'خطوات الإبلاغ', 'خطوات الابلاغ', 'تقديم بلاغ', 'أقدم بلاغ', 'ابلغ عن', 'أبلغ عن', 'إبلاغ عن', 'حفظ الأدلة', 'الادلة الرقمية', 'الأدلة الرقمية', 'reporting steps', 'file a report', 'digital evidence'],
            'ar' => "إذا رغبتِ في الإبلاغ عن عنف أو تحرش أو تهديد إلكتروني في مصر، فهذه خطوات عامة تساعدكِ على الاستعداد، وقد تختلف الإجراءات بحسب الواقعة والجهة:\n\n1. احفظي الأدلة كما هي: التقطي صورًا واضحة للشاشة تُظهر الحساب أو رقم الهاتف والرسائل والتاريخ والوقت، واحتفظي بروابط الحساب والمنشورات وأي بيانات متاحة. لا تعدّلي الصور ولا تحذفي المحادثة الأصلية أو الرسائل من الجهاز. احتفظي بنسخة آمنة، ولا تنشري المحتوى الخاص علنًا.\n\n2. دوّني تسلسلًا مختصرًا للوقائع: متى بدأت، وما الذي حدث، وأي طلب أو تهديد أو نشر للمحتوى. احتفظي بأسماء الشهود أو أرقام البلاغات السابقة إن وجدت.\n\n3. قدّمي البلاغ إلى قسم الشرطة أو إلى الجهة المختصة بجرائم تقنية المعلومات عبر القنوات الرسمية المتاحة. تحققي من عنوان الجهة ومواعيدها وإجراءاتها من مصدر رسمي قبل الذهاب؛ فقد تختلف طرق الإحالة والفحص من حالة لأخرى. اشرحي الوقائع وقدّمي الأدلة، واسألي عن طريقة توثيقها وفحص الجهاز وفق الإجراءات الرسمية.\n\n4. اطلبي رقم المحضر أو البلاغ، وأي رقم صادر أو مرجع متابعة متاح، واحتفظي به لمراجعة الجهة المختصة أو النيابة. لا يمكن ضمان تحديد هوية صاحب الحساب أو نتيجة التحقيق؛ فهذا يتوقف على الأدلة والإجراءات الفنية والقانونية.\n\n5. اسألي الجهة المختصة عن كيفية حماية بياناتكِ وخصوصيتكِ أثناء تقديم البلاغ والتحقيق، ولا تعتمدي على منشورات الإنترنت لتأكيد السرية أو نتيجة القضية. يمكنكِ اصطحاب شخص تثقين به أو استشارة محامٍ. إذا كان هناك خطر فوري، اطلبي مساعدة عاجلة من شخص موثوق أو خدمات الطوارئ.\n\nهذه معلومات عامة وليست استشارة قانونية؛ استشيري محاميًا أو جهة رسمية لتوجيه يناسب حالتكِ.",
            'en' => "If you want to report online abuse, harassment or threats in Egypt, these general steps may help you prepare. Procedures can vary by case and authority:\n\n1. Preserve evidence in its original form. Take clear screenshots showing the account or phone number, messages, and dates/times. Save profile and post URLs and other available details. Do not edit screenshots or delete the original conversation or messages from your device. Keep a secure copy and do not publish private content publicly.\n\n2. Write a brief timeline: when the conduct began, what happened, and any demands, threats or sharing of content. Note witnesses or earlier report references, if any.\n\n3. Report to a police station or the competent cybercrime authority through its official channels. Verify the office location, hours and process using an official source before visiting; referral and examination procedures can differ. Explain what happened, provide the evidence, and ask how it can be documented and how any device examination will follow official procedures.\n\n4. Ask for the report/reference number and any follow-up reference, and keep it for contacting the authority or prosecution. Identification of an account owner or the outcome of an investigation cannot be guaranteed; it depends on evidence and legal and technical procedures.\n\n5. Ask the authority how your identity and privacy can be protected during reporting and investigation. Do not rely on online posts as a guarantee of confidentiality or case outcomes. You may bring someone you trust or consult a lawyer. If you face immediate danger, seek urgent help from someone you trust or emergency services.\n\nThis is general information, not legal advice. Consult a qualified lawyer or official authority for guidance about your circumstances."
        ],
        [
            'keys' => ['306 مكرر', 'المادة 306', 'المادة 327', 'المادة 25', 'المادة 26', '175 لسنة 2018', 'قانون مكافحة جرائم تقنية المعلومات', 'القوانين المصرية', 'المواد القانونية', 'egyptian law', 'article 306', 'article 327', 'article 25', 'article 26'],
            'ar' => "معلومات عامة عن مواد القوانين المصرية المتعلقة بالتحرش والتهديد الإلكتروني:\n\n• المادة 306 مكرر (أ) من قانون العقوبات: وفق النص الوارد، تعاقب على التعرض للغير بأمور أو إيحاءات أو تلميحات جنسية أو إباحية، بأي وسيلة بما فيها وسائل الاتصالات الإلكترونية، بالحبس من سنتين إلى أربع سنوات وغرامة من 100 ألف إلى 200 ألف جنيه أو بإحدى العقوبتين.\n\n• المادة 306 مكرر (ب): إذا ارتكبت الجريمة بقصد الحصول على منفعة ذات طبيعة جنسية، تكون العقوبة السجن مدة لا تقل عن خمس سنوات وغرامة من 200 ألف إلى 300 ألف جنيه أو بإحدى العقوبتين. وتصل العقوبة إلى السجن مدة لا تقل عن سبع سنوات في الحالات المشددة المذكورة بالنص، ومنها وجود سلطة أو ضغط أو ارتكاب الجريمة من شخصين أو أكثر أو حمل أحدهم سلاحًا.\n\n• المادة 327 من قانون العقوبات: تتناول التهديد كتابةً بارتكاب جريمة جسيمة أو بإفشاء أمور مخدشة بالشرف، وتختلف العقوبة بحسب وجود طلب أو تكليف وبحسب وسيلة التهديد وجسامته.\n\n• المادة 25 من قانون مكافحة جرائم تقنية المعلومات رقم 175 لسنة 2018: تتناول الاعتداء على المبادئ والقيم الأسرية، وانتهاك الخصوصية أو إرسال رسائل إلكترونية بكثافة دون موافقة، ونشر معلومات أو صور تنتهك خصوصية شخص دون رضاه؛ والعقوبة الواردة بالنص الحبس مدة لا تقل عن ستة أشهر وغرامة من 50 ألفًا إلى 100 ألف جنيه أو بإحدى العقوبتين.\n\n• المادة 26 من القانون نفسه: تتناول استعمال تقنية المعلومات عمدًا لمعالجة بيانات شخصية وربطها بمحتوى منافٍ للآداب أو عرضها بما يمس الشرف أو الاعتبار؛ والعقوبة الواردة بالنص الحبس من سنتين إلى خمس سنوات وغرامة من 100 ألف إلى 300 ألف جنيه أو بإحدى العقوبتين.\n\nهذه معلومات عامة وليست استشارة قانونية، وقد تتغير النصوص أو العقوبات بتعديلات لاحقة. تحققي من النص الرسمي الساري واستشيري محاميًا أو جهة مختصة لتقييم الحالة. إذا كنتِ في خطر فوري، اطلبي مساعدة شخص تثقين به أو خدمات الطوارئ. يمكنكِ سؤالي عن خطوات الإبلاغ أو الأدلة الرقمية التي يُستحسن حفظها.",
            'en' => "General information about Egyptian laws relating to online harassment and threats:\n\n• Penal Code Article 306 bis (a): The text provided describes liability for subjecting someone to sexual or pornographic acts, insinuations or gestures by any means, including electronic communications. It states imprisonment for two to four years and a fine of EGP 100,000–200,000, or either penalty.\n\n• Article 306 bis (b): Where the conduct is committed to obtain a sexual benefit, the text states imprisonment for at least five years and a fine of EGP 200,000–300,000, or either penalty. It states a minimum of seven years in the listed aggravated circumstances, including authority or pressure, multiple offenders, or a weapon.\n\n• Penal Code Article 327: Covers written threats of serious crimes or disclosure of matters harmful to honour. The penalty depends on the nature and seriousness of the threat and whether it includes a demand.\n\n• Information Technology Crimes Law No. 175 of 2018, Article 25: Covers violations of family values or privacy, repeated electronic messages without consent, and publishing information or images that violate privacy without consent. The provided text states at least six months' imprisonment and a fine of EGP 50,000–100,000, or either penalty.\n\n• Article 26 of that law: Covers intentionally using information technology to process another person's personal data in connection with immoral content or in a way that harms their honour or reputation. The provided text states imprisonment for two to five years and a fine of EGP 100,000–300,000, or either penalty.\n\nThis is general information, not legal advice. Laws and penalties may have changed; check the current official text and consult a qualified Egyptian lawyer or authority about a specific case. If you are in immediate danger, contact someone you trust or emergency services. You can ask me about reporting steps or digital evidence to preserve."
        ],
        ['keys' => ['تحرش', 'harassment', 'cyberbullying'], 'ar' => 'التحرش الإلكتروني سلوك مؤذٍ يحدث عبر الإنترنت، مثل الرسائل أو التهديدات أو نشر محتوى خاص دون إذن. أنتِ تستحقين الاحترام، وليس من حق أي شخص إيذاؤك.', 'en' => 'Online harassment is harmful behaviour such as threatening messages or sharing private content without consent. You deserve respect, and no one has the right to harm you.'],
        ['keys' => ['ابتزاز', 'ابتز', 'blackmail', 'sextortion'], 'ar' => 'إذا كان هناك من يهددكِ أو يبتزكِ، فلا تستجيبي للتهديدات ولا ترسلي أموالًا أو محتوى إضافيًا. احتفظي بالأدلة بأمان، ولا تواجهي الشخص وحدكِ. استعيني بشخص تثقين به أو بجهة مختصة لمساعدتكِ في الإبلاغ.', 'en' => 'If someone is threatening or blackmailing you, do not comply or send money or more content. Save evidence safely, do not confront them alone, and ask someone you trust or a qualified support service to help you report it.'],
        ['keys' => ['تهديد', 'threat'], 'ar' => 'خذي التهديد على محمل الجد. احتفظي بالرسائل أو لقطات الشاشة إذا كان ذلك آمنًا، ثم احظري الحساب وأبلغي عنه. إذا كنتِ في خطر فوري، فاتصلي بشخص تثقين به أو بخدمات الطوارئ المحلية.', 'en' => 'Take threats seriously. Save messages or screenshots if it is safe, then block and report the account. If you are in immediate danger, contact someone you trust or local emergency services.'],
        ['keys' => ['ابلاغ', 'إبلاغ', 'report'], 'ar' => 'افتحي الحساب أو الرسالة، واختاري «إبلاغ» أو Report، ثم حددي السبب المناسب واتبعي التعليمات. تختلف الخطوات من تطبيق إلى آخر. ويمكنكِ الاستعانة بشخص تثقين به عند الحاجة.', 'en' => 'Open the account or message, choose “Report,” select the reason that best fits and follow the instructions. Steps vary by app. Someone you trust can help if needed.'],
        ['keys' => ['حظر', 'احظر', 'block'], 'ar' => 'لحظر حساب، افتحي الملف الشخصي أو المحادثة وابحثي عن خيار «حظر» أو Block. بعد ذلك، راجعي إعدادات الخصوصية وأبلغي عن الحساب إذا كان يسبب لكِ الأذى.', 'en' => 'To block an account, open its profile or conversation and look for “Block.” Then review your privacy settings and report the account if it is harming you.'],
        ['keys' => ['خصوصية', 'privacy'], 'ar' => 'اجعلي حسابكِ خاصًا إذا كان ذلك متاحًا، وراجعي من يمكنه رؤية منشوراتكِ أو مراسلتكِ. استخدمي كلمة مرور قوية ومختلفة لكل حساب، وفعّلي التحقق بخطوتين.', 'en' => 'Make your account private if possible, review who can see your posts or message you, use a unique strong password for every account and turn on two-step verification.'],
        ['keys' => ['كلمة المرور', 'كلمه المرور', 'password'], 'ar' => 'استخدمي كلمة مرور قوية وفريدة، ولا تشاركيها مع أي شخص. فعّلي التحقق بخطوتين، وتجنبي الضغط على روابط تسجيل الدخول المشبوهة.', 'en' => 'Use a strong, unique password and never share it. Turn on two-step verification and avoid suspicious sign-in links.'],
        ['keys' => ['صور', 'photo', 'image', 'picture'], 'ar' => 'لا ترسلي صورًا خاصة استجابةً للضغط أو التهديد. إذا نشر أحدهم صورة لكِ دون موافقتكِ، فاحتفظي برابط المنشور والأدلة بأمان إن أمكن، وأبلغي المنصة واستعيني بشخص تثقين به أو بجهة مختصة.', 'en' => 'Never send private images because of pressure or threats. If someone shares an image of you without consent, safely save the post link and evidence if possible, report it to the platform and contact someone you trust or a qualified support service.'],
        ['keys' => ['خايف', 'خايفة', 'scared', 'afraid'], 'ar' => 'آسفة لأنكِ تمرين بهذا الموقف. لستِ مضطرة إلى التعامل معه وحدكِ. تحدثي إلى شخص تثقين به أو إلى مختصة اجتماعية أو جهة دعم مناسبة.', 'en' => 'I am sorry you are going through this. You do not have to handle it alone. Talk to someone you trust, a counsellor or an appropriate support service.'],
        ['keys' => ['مساعدة', 'help'], 'ar' => 'بالتأكيد، أستطيع مشاركة معلومات عامة عن التحرش الإلكتروني والابتزاز والحظر والإبلاغ والخصوصية. اختاري موضوعًا من الأسئلة المقترحة أو اكتبي سؤالك.', 'en' => 'I can share general information about online harassment, blackmail, blocking, reporting and privacy. Choose a suggested topic or type your question.'],
        ['keys' => ['مرحبا', 'مرحبًا', 'hello'], 'ar' => 'أهلًا بكِ في «اختاري طريقك» 💜 أنا هنا لمساعدتكِ بمعلومات عن الأمان الرقمي.', 'en' => 'Welcome to Choose Your Path 💜 I am here to share information about digital safety.'],
        ['keys' => ['السلام عليكم'], 'ar' => 'وعليكم السلام 💜 أهلًا بكِ في «اختاري طريقك». كيف أستطيع مساعدتكِ؟', 'en' => 'Peace be upon you 💜 Welcome to Choose Your Path. How can I help?']
    ];

    $reply = tr(
        'هل يمكنكِ توضيح سؤالك أكثر؟ أستطيع مشاركة معلومات عامة عن التحرش الإلكتروني والابتزاز والحظر والإبلاغ وحماية الخصوصية. إذا كنتِ تمرين بموقف صعب، فتواصلي مع شخص تثقين به أو جهة دعم مختصة.',
        'Could you tell me a little more? I can share general information about online harassment, blackmail, blocking, reporting and privacy. If you are going through a difficult situation, contact someone you trust or a qualified support service.'
    );

    foreach ($answers as $answer) {
        foreach ($answer['keys'] as $keyword) {
            if (mb_stripos($text, $keyword, 0, 'UTF-8') !== false) {
                $reply = $answer[page_language()];
                break 2;
            }
        }
    }

    echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= page_language() ?>" dir="<?= page_direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= tr('المساعد الرقمي | اختاري طريقك', 'Digital guide | Choose Your Path') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="chat-container">
    <header class="chat-header">
        <div class="language-switch">
            <a href="<?= htmlspecialchars(language_url('ar'), ENT_QUOTES, 'UTF-8') ?>" lang="ar" aria-current="<?= page_language() === 'ar' ? 'true' : 'false' ?>">عربي</a>
            <a href="<?= htmlspecialchars(language_url('en'), ENT_QUOTES, 'UTF-8') ?>" lang="en" aria-current="<?= page_language() === 'en' ? 'true' : 'false' ?>">EN</a>
        </div>
        <h1>💜 <?= tr('المساعد الرقمي', 'Digital guide') ?></h1>
        <p><?= tr('اختاري طريقك نحو الأمان الرقمي', 'Your guide to digital safety') ?></p>
    </header>

    <div class="chat-messages" id="chatMessages" aria-live="polite">
        <div class="message bot-message"><?= tr(
            'أهلًا بكِ! أنا المساعد الرقمي لمنصة «اختاري طريقك». يمكنكِ سؤالي عن التحرش الإلكتروني أو الحظر أو الإبلاغ أو الخصوصية.',
            'Hello! I am the digital guide for Choose Your Path. Ask me about online harassment, blocking, reporting or privacy.'
        ) ?></div>
    </div>

    <div class="quick-questions">
        <button type="button" data-question="<?= htmlspecialchars(tr('ما هو التحرش الإلكتروني؟', 'What is online harassment?'), ENT_QUOTES, 'UTF-8') ?>"><?= tr('التحرش الإلكتروني', 'Online harassment') ?></button>
        <button type="button" data-question="<?= htmlspecialchars(tr('كيف أبلغ عن التحرش؟', 'How do I report harassment?'), ENT_QUOTES, 'UTF-8') ?>"><?= tr('الإبلاغ', 'Reporting') ?></button>
        <button type="button" data-question="<?= htmlspecialchars(tr('كيف أحظر حسابًا؟', 'How do I block an account?'), ENT_QUOTES, 'UTF-8') ?>"><?= tr('الحظر', 'Blocking') ?></button>
        <button type="button" data-question="<?= htmlspecialchars(tr('كيف أحمي خصوصيتي؟', 'How can I protect my privacy?'), ENT_QUOTES, 'UTF-8') ?>"><?= tr('حماية الخصوصية', 'Privacy') ?></button>
        <button type="button" data-question="<?= htmlspecialchars(tr('ماذا أفعل لو حد بيبتزني؟', 'What should I do if someone blackmails me?'), ENT_QUOTES, 'UTF-8') ?>"><?= tr('الابتزاز الإلكتروني', 'Online blackmail') ?></button>
        <button type="button" data-question="<?= htmlspecialchars(tr('ما خطوات تحرير محضر عن التحرش أو التهديد الإلكتروني؟', 'What are the steps to report online harassment or threats?'), ENT_QUOTES, 'UTF-8') ?>"><?= tr('خطوات تقديم بلاغ', 'How to report') ?></button>
        <button type="button" data-question="<?= htmlspecialchars(tr('ما القوانين المصرية المتعلقة بالتحرش والتهديد الإلكتروني؟', 'What Egyptian laws address online harassment and threats?'), ENT_QUOTES, 'UTF-8') ?>"><?= tr('القوانين المصرية', 'Egyptian laws') ?></button>
    </div>

    <form class="chat-form" id="chatForm">
        <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($_SESSION['chat_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" id="userInput" name="message"
               placeholder="<?= tr('اكتبي سؤالك هنا...', 'Type your question here...') ?>"
               maxlength="1000" autocomplete="off" required>
        <button type="submit" id="sendButton"><?= tr('إرسال', 'Send') ?></button>
    </form>

    <p class="notice"><?= tr(
        'يقدم المساعد معلومات عامة فقط، ولا يُعد خدمة طوارئ. لا تشاركي كلمات المرور أو البيانات الشخصية هنا. إذا كنتِ تتعرضين للأذى، فتواصلي مع شخص تثقين به أو جهة دعم مختصة.',
        'This guide provides general information and is not an emergency service. Do not share passwords or personal information here. If you are being harmed, seek help from a trusted adult.'
    ) ?></p>
</div>
<a class="back-link" href="../index.php"><?= tr('العودة إلى الصفحة الرئيسية', 'Back to home') ?></a>

<script>
const chatForm = document.getElementById('chatForm');
const userInput = document.getElementById('userInput');
const chatMessages = document.getElementById('chatMessages');
const sendButton = document.getElementById('sendButton');
const csrfToken = document.getElementById('csrfToken').value;
const sendError = <?= json_encode(tr('تعذر إرسال الرسالة.', 'Could not send the message.'), JSON_UNESCAPED_UNICODE) ?>;
const retryText = <?= json_encode(tr('حاولي مرة أخرى.', 'Please try again.'), JSON_UNESCAPED_UNICODE) ?>;
const connectionError = <?= json_encode(tr('حصلت مشكلة في الاتصال. تأكدي إن XAMPP شغال وحاولي مرة أخرى.', 'Connection problem. Check that the server is running and try again.'), JSON_UNESCAPED_UNICODE) ?>;

function addMessage(text, sender) {
    const message = document.createElement('div');
    message.className = 'message ' + sender;
    message.textContent = text;
    chatMessages.appendChild(message);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

async function sendMessage(text) {
    text = text.trim();
    if (!text || sendButton.disabled) return;

    addMessage(text, 'user-message');
    userInput.value = '';
    sendButton.disabled = true;

    try {
        const formData = new FormData();
        formData.append('message', text);
        const response = await fetch('chat.php', {
            method: 'POST',
            body: formData,
            headers: {'X-CSRF-Token': csrfToken}
        });
        const data = await response.json();
        if (!response.ok) throw new Error(sendError);
        addMessage(data.reply || retryText, 'bot-message');
    } catch (error) {
        addMessage(connectionError, 'bot-message');
    } finally {
        sendButton.disabled = false;
        userInput.focus();
    }
}

chatForm.addEventListener('submit', function(event) {
    event.preventDefault();
    sendMessage(userInput.value);
});

document.querySelectorAll('[data-question]').forEach(function(button) {
    button.addEventListener('click', function() {
        sendMessage(button.dataset.question);
    });
});
</script>
</body>
</html>
