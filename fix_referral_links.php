<?php
// فایل موقت برای اصلاح لینک‌های رفرال در پروژه

// بخش‌های حساس فایل long_polling_bot.php را پیدا و اصلاح می‌کند
$file = __DIR__ . '/long_polling_bot.php';
$content = file_get_contents($file);

// لیست الگوهای مورد نظر برای تغییر
$patterns = [
    // الگوی 1: تغییر کد لینک رفرال در بخش لیست خالی زیرمجموعه‌ها
    '/(\$message \.\= "برای دعوت از دوستان، لینک اختصاصی خود را به آنها ارسال کنید:\\n";)(\s+)\/\/ دریافت اطلاعات ربات\s+\$botInfo = getBotInfo\(\$_ENV\[\'TELEGRAM_TOKEN\'\]\);\s+\$botUsername = isset\(\$botInfo\[\'username\'\]\) \? \$botInfo\[\'username\'\] : \'your_bot\';\s+\$message \.\= "https:\/\/t\.me\/" \. \$botUsername \. "\?start=" \. \$userData\[\'id\'\];/is' 
    => '$1$2// استفاده از تابع جدید برای ساخت لینک رفرال یکپارچه$2$message .= generateReferralLink($_ENV[\'TELEGRAM_TOKEN\'], $userData[\'id\']);',

    // الگوی 2: تغییر کد لینک رفرال در بخش لیست زیرمجموعه‌ها
    '/(\$message \.\= "لینک اختصاصی شما برای دعوت از دوستان:\\n";)(\s+)\/\/ دریافت اطلاعات ربات\s+\$botInfo = getBotInfo\(\$_ENV\[\'TELEGRAM_TOKEN\'\]\);\s+\$botUsername = isset\(\$botInfo\[\'username\'\]\) \? \$botInfo\[\'username\'\] : \'your_bot\';\s+\$message \.\= "https:\/\/t\.me\/" \. \$botUsername \. "\?start=" \. \$userData\[\'id\'\] \. "\\n\\n";/is'
    => '$1$2// استفاده از تابع جدید برای ساخت لینک رفرال یکپارچه$2$message .= generateReferralLink($_ENV[\'TELEGRAM_TOKEN\'], $userData[\'id\']) . "\n\n";',
];

// اعمال تغییرات
foreach ($patterns as $pattern => $replacement) {
    $content = preg_replace($pattern, $replacement, $content);
}

// ذخیره نتیجه
file_put_contents($file, $content);

echo "اصلاح لینک‌های رفرال در فایل $file انجام شد.\n";
