-- اضافه کردن ستون type به جدول users
-- این ستون برای مشخص کردن نوع کاربر استفاده می‌شود (عادی، ادمین، مالک)
ALTER TABLE users ADD COLUMN IF NOT EXISTS type VARCHAR(50) NOT NULL DEFAULT 'user';

-- تنظیم کاربر با آیدی 286420965 به عنوان مالک (owner)
UPDATE users SET type = 'owner' WHERE telegram_id = 286420965;