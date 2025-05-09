-- جدول برای قفل آیدی‌ها
CREATE TABLE IF NOT EXISTS locked_usernames (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    reason TEXT,
    admin_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_locked_usernames_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایجاد ایندکس برای نام کاربری‌های قفل شده
CREATE UNIQUE INDEX IF NOT EXISTS idx_locked_usernames_username ON locked_usernames (username);

-- جدول تنظیمات ربات
CREATE TABLE IF NOT EXISTS bot_settings (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    value TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

-- ایجاد ایندکس برای نام تنظیمات
CREATE UNIQUE INDEX IF NOT EXISTS idx_bot_settings_name ON bot_settings (name);

-- جدول تراکنش‌های دلتا کوین (برای تغییرات مدیران)
CREATE TABLE IF NOT EXISTS delta_coin_transactions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    amount FLOAT NOT NULL,
    reason TEXT,
    admin_id INT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_delta_coin_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_delta_coin_transactions_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول تراکنش‌های جام (برای تغییرات مدیران)
CREATE TABLE IF NOT EXISTS trophy_transactions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    amount FLOAT NOT NULL,
    reason TEXT,
    admin_id INT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_trophy_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_trophy_transactions_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- جدول برای درخواست برداشت وجه
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'bank' or 'trx'
    wallet_address TEXT,
    bank_card_number TEXT,
    bank_sheba TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'rejected'
    processed_by INT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_withdrawal_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_withdrawal_requests_admin FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- میدان‌های اضافی برای جدول admin_permissions
ALTER TABLE admin_permissions ADD COLUMN IF NOT EXISTS can_manage_settings BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE admin_permissions ADD COLUMN IF NOT EXISTS can_view_users BOOLEAN NOT NULL DEFAULT true;
ALTER TABLE admin_permissions ADD COLUMN IF NOT EXISTS can_manage_withdrawals BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE admin_permissions ADD COLUMN IF NOT EXISTS can_send_forwards BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE admin_permissions ADD COLUMN IF NOT EXISTS can_lock_usernames BOOLEAN NOT NULL DEFAULT false;

-- افزودن ستون‌های جدید به جدول users_extra برای جام‌ها
ALTER TABLE users_extra ADD COLUMN IF NOT EXISTS trophies INT NOT NULL DEFAULT 0;

-- ایجاد تنظیمات اولیه
INSERT INTO bot_settings (name, value) VALUES
('bot_active', '1'),
('delta_coin_price', '1000'),
('referral_commission_initial', '0.5'),
('referral_commission_first_win', '1.5'),
('referral_commission_profile_completion', '3'),
('referral_commission_thirty_wins', '5'),
('min_withdrawal_amount', '50'),
('withdrawal_step', '10')
ON CONFLICT (name) DO NOTHING;