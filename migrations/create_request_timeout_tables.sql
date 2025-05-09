-- جدول برای درخواست‌های بازی و تایم‌اوت آن‌ها
CREATE TABLE IF NOT EXISTS game_requests (
    id SERIAL PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'accepted', 'rejected'
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_game_requests_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_game_requests_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایجاد ایندکس برای جستجوی سریع درخواست‌های بازی
CREATE INDEX IF NOT EXISTS idx_game_requests_sender_receiver ON game_requests (sender_id, receiver_id);
CREATE INDEX IF NOT EXISTS idx_game_requests_status ON game_requests (status);

-- جدول برای درخواست‌های دوستی و تایم‌اوت آن‌ها
CREATE TABLE IF NOT EXISTS friend_requests (
    id SERIAL PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'accepted', 'rejected'
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_friend_requests_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friend_requests_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایجاد ایندکس برای جستجوی سریع درخواست‌های دوستی
CREATE INDEX IF NOT EXISTS idx_friend_requests_sender_receiver ON friend_requests (sender_id, receiver_id);
CREATE INDEX IF NOT EXISTS idx_friend_requests_status ON friend_requests (status);