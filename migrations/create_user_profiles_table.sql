-- جدول برای پروفایل کاربران
CREATE TABLE IF NOT EXISTS user_profiles (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    photo VARCHAR(255) NULL,
    photo_approved BOOLEAN DEFAULT FALSE,
    name VARCHAR(100) NULL,
    gender VARCHAR(10) NULL, -- 'male' یا 'female'
    age INT NULL,
    bio TEXT NULL,
    bio_approved BOOLEAN DEFAULT FALSE,
    province VARCHAR(50) NULL,
    city VARCHAR(50) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    phone VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایندکس برای جستجوی سریع
CREATE INDEX IF NOT EXISTS idx_user_profiles_user_id ON user_profiles (user_id);

-- جدول برای نگهداری ری‌اکشن‌های اضافه شده
CREATE TABLE IF NOT EXISTS reactions (
    id SERIAL PRIMARY KEY,
    emoji VARCHAR(10) NOT NULL,
    description VARCHAR(50) NOT NULL,
    emoji_type VARCHAR(20) NOT NULL DEFAULT 'default', -- 'default', 'ios', 'android', 'custom'
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- اضافه کردن ری‌اکشن‌های پیش‌فرض
INSERT INTO reactions (emoji, description, emoji_type)
VALUES 
('👍', 'لایک', 'ios'),
('👎', 'دیسلایک', 'ios'),
('❤️', 'قلب', 'ios'),
('😍', 'عاشق', 'ios'),
('😂', 'خنده', 'ios'),
('😭', 'گریه', 'ios'),
('😡', 'عصبانی', 'ios'),
('🔥', 'آتشین', 'ios'),
('🎉', 'جشن', 'ios'),
('👏', 'تشویق', 'ios')
ON CONFLICT DO NOTHING;

-- جدول برای ری‌اکشن‌های کاربران در چت
CREATE TABLE IF NOT EXISTS user_reactions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    message_id BIGINT NOT NULL,
    reaction_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_reactions_reaction FOREIGN KEY (reaction_id) REFERENCES reactions(id) ON DELETE CASCADE
);

-- ایندکس برای جستجوی سریع
CREATE INDEX IF NOT EXISTS idx_user_reactions_message_id ON user_reactions (message_id);
CREATE INDEX IF NOT EXISTS idx_user_reactions_user_id ON user_reactions (user_id);

-- جدول برای تایمر‌های چت بعد از بازی
CREATE TABLE IF NOT EXISTS post_game_chats (
    id SERIAL PRIMARY KEY,
    match_id INT NOT NULL,
    chat_end_time TIMESTAMP NOT NULL,
    extended BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_post_game_chats_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
);

-- جدول برای وضعیت چت کاربران
CREATE TABLE IF NOT EXISTS chat_status (
    id SERIAL PRIMARY KEY,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_deactivation TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_chat_status_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_status_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایندکس برای جستجوی سریع
CREATE INDEX IF NOT EXISTS idx_chat_status_match_user ON chat_status (match_id, user_id);

-- جدول برای دلتا کوین روزانه
CREATE TABLE IF NOT EXISTS daily_coins (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    claim_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_daily_coins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایندکس برای جستجوی سریع
CREATE INDEX IF NOT EXISTS idx_daily_coins_user_date ON daily_coins (user_id, claim_date);

-- اضافه کردن تنظیمات جدید به جدول تنظیمات
INSERT INTO bot_settings (name, value)
VALUES 
('daily_coin_channels', '{"channels": ["-100123456789", "-100987654321"]}'),
('daily_coin_min', '0.1'),
('daily_coin_max', '1.0'),
('admin_channel_id', '-100123456789'),
('post_game_chat_time', '30'), -- زمان چت بعد از بازی (ثانیه)
('extended_chat_time', '300') -- زمان چت گسترش یافته (ثانیه)
ON CONFLICT (name) DO UPDATE SET value = EXCLUDED.value;