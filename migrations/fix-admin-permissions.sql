-- ابتدا جدول admin_permissions را ایجاد می‌کنیم که در مایگریشن قبلی وجود نداشت
CREATE TABLE IF NOT EXISTS admin_permissions (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- سپس مقادیر پیش‌فرض را اضافه می‌کنیم
INSERT INTO admin_permissions (name, description) 
VALUES
    ('manage_users', 'مدیریت کاربران'),
    ('manage_settings', 'مدیریت تنظیمات'),
    ('view_statistics', 'مشاهده آمار'),
    ('manage_transactions', 'مدیریت تراکنش‌ها'),
    ('manage_games', 'مدیریت بازی‌ها'),
    ('broadcast_message', 'ارسال پیام عمومی'),
    ('view_logs', 'مشاهده لاگ‌ها')
ON CONFLICT (name) DO NOTHING;

-- اضافه کردن ستون‌های مورد نیاز به جدول bot_settings اگر وجود نداشته باشند
ALTER TABLE bot_settings ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE bot_settings ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT false;
ALTER TABLE bot_settings ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP;