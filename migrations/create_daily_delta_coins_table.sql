-- ایجاد جدول daily_delta_coins برای مدیریت دلتا کوین روزانه
CREATE TABLE IF NOT EXISTS "daily_delta_coins" (
    "id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL,
    "amount" NUMERIC(10, 2) NOT NULL DEFAULT 0,
    "claim_date" DATE NOT NULL,
    "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

-- ایجاد ایندکس برای جستجوی سریع
CREATE INDEX IF NOT EXISTS "daily_delta_coins_user_id_claim_date_idx" ON "daily_delta_coins" ("user_id", "claim_date");