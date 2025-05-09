
-- Add admin user if not exists
INSERT INTO users (telegram_id, username, type, created_at, updated_at)
SELECT 286420965, 'pooya12345678910', 'admin', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE telegram_id = 286420965
);
