-- ایجاد جدول referrals برای مدیریت سیستم زیرمجموعه‌ها
CREATE TABLE IF NOT EXISTS "referrals" (
    "id" SERIAL PRIMARY KEY,
    "referrer_id" INTEGER NOT NULL,
    "referee_id" INTEGER NOT NULL,
    "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "started_rewarded" BOOLEAN NOT NULL DEFAULT FALSE,
    "first_win_rewarded" BOOLEAN NOT NULL DEFAULT FALSE,
    "profile_completed_rewarded" BOOLEAN NOT NULL DEFAULT FALSE,
    "thirty_wins_rewarded" BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY ("referrer_id") REFERENCES "users" ("id") ON DELETE CASCADE,
    FOREIGN KEY ("referee_id") REFERENCES "users" ("id") ON DELETE CASCADE,
    UNIQUE ("referee_id")
);

-- ایجاد ایندکس برای جستجوی سریع
CREATE INDEX IF NOT EXISTS "referrals_referrer_id_idx" ON "referrals" ("referrer_id");
CREATE INDEX IF NOT EXISTS "referrals_referee_id_idx" ON "referrals" ("referee_id");