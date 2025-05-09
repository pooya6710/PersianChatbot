-- ایجاد جدول آمار ادمین
CREATE TABLE IF NOT EXISTS admin_statistics (
    id SERIAL PRIMARY KEY,
    date DATE NOT NULL DEFAULT CURRENT_DATE,
    total_users INT NOT NULL DEFAULT 0,
    active_users INT NOT NULL DEFAULT 0,
    total_games INT NOT NULL DEFAULT 0,
    active_games INT NOT NULL DEFAULT 0,
    new_users INT NOT NULL DEFAULT 0,
    completed_games INT NOT NULL DEFAULT 0,
    total_delta_coins BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ایجاد جدول دسترسی‌های ادمین
CREATE TABLE IF NOT EXISTS admin_permissions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    can_send_broadcasts BOOLEAN NOT NULL DEFAULT false,
    can_manage_admins BOOLEAN NOT NULL DEFAULT false,
    can_manage_games BOOLEAN NOT NULL DEFAULT false,
    can_manage_users BOOLEAN NOT NULL DEFAULT false,
    can_view_statistics BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_admin_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایجاد جدول پیام‌های همگانی
CREATE TABLE IF NOT EXISTS broadcast_messages (
    id SERIAL PRIMARY KEY,
    admin_id INT NOT NULL,
    message_type VARCHAR(50) NOT NULL DEFAULT 'text',
    message_text TEXT,
    media_id VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    total_sent INT NOT NULL DEFAULT 0,
    total_failed INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    CONSTRAINT fk_broadcast_messages_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایجاد جدول قفل‌های گروه/کانال
CREATE TABLE IF NOT EXISTS channel_locks (
    id SERIAL PRIMARY KEY,
    channel_id VARCHAR(255) NOT NULL,
    channel_name VARCHAR(255) NOT NULL,
    channel_type VARCHAR(50) NOT NULL DEFAULT 'channel',
    token VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);