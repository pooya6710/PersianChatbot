-- جدول برای درخواست‌های برداشت دلتا کوین
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL, -- مقدار دلتا کوین
    type VARCHAR(20) NOT NULL, -- نوع برداشت: 'bank' یا 'trx'
    bank_card_number VARCHAR(20) NULL, -- شماره کارت بانکی (برای نوع بانک)
    wallet_address VARCHAR(100) NULL, -- آدرس کیف پول (برای نوع ترون)
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- وضعیت: 'pending', 'completed', 'rejected'
    processed_by INT NULL, -- شناسه ادمین پردازش کننده
    processed_at TIMESTAMP NULL, -- زمان پردازش
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    transaction_receipt VARCHAR(255) NULL, -- رسید تراکنش
    notes TEXT NULL, -- یادداشت‌های ادمین
    CONSTRAINT fk_withdrawal_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_withdrawal_requests_admin FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ایجاد ایندکس برای جستجوی سریع درخواست‌های برداشت
CREATE INDEX IF NOT EXISTS idx_withdrawal_requests_user ON withdrawal_requests (user_id);
CREATE INDEX IF NOT EXISTS idx_withdrawal_requests_status ON withdrawal_requests (status);
CREATE INDEX IF NOT EXISTS idx_withdrawal_requests_created_at ON withdrawal_requests (created_at);

-- اضافه کردن تنظیمات برداشت به جدول تنظیمات
INSERT INTO bot_settings (name, value, description, is_public)
VALUES 
('min_withdrawal_amount', '50', 'حداقل مقدار برای برداشت دلتا کوین', false),
('withdrawal_step', '10', 'مضرب مقدار برداشت (باید به عنوان مثال 10تایی درخواست برداشت کنند)', false),
('delta_coin_price', '1000', 'قیمت هر دلتا کوین به تومان', false)
ON CONFLICT (name) DO NOTHING;