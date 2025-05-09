<?php

namespace application\controllers;

use Application\Model\DB;

class OptionController
{
    public $channels;
    public $forced_to_join;

    public function __construct(){
        try {
            // بررسی وجود جدول options
            $tableExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public'
                AND table_name = 'options'
            ) as exists");
            
            // اگر جدول وجود نداشت آن را بسازیم
            if (!$tableExists[0]['exists']) {
                $this->createOptionsTable();
            }
            
            // بررسی وجود ستون forced_to_join
            $columnExists = DB::rawQuery("SELECT EXISTS (
                SELECT FROM information_schema.columns 
                WHERE table_schema = 'public'
                AND table_name = 'options'
                AND column_name = 'forced_to_join'
            ) as exists");
            
            // اگر ستون وجود نداشت آن را اضافه کنیم
            if (!$columnExists[0]['exists']) {
                DB::rawQuery("ALTER TABLE options ADD COLUMN forced_to_join TEXT DEFAULT '[]'");
            }
            
            // بررسی وجود داده برای channels
            $channelsRow = DB::table('options')->where('name', 'channels')->first();
            if (!$channelsRow) {
                DB::table('options')->insert([
                    'name' => 'channels',
                    'value' => '[]',
                    'channels' => '[]',
                    'forced_to_join' => '[]'
                ]);
            }
            
            // خواندن اطلاعات از دیتابیس
            $this->channels = json_decode(DB::table('options')->select('channels')->first()['channels'] ?? '[]', true) ?: [];
            $this->forced_to_join = json_decode(DB::table('options')->select('forced_to_join')->first()['forced_to_join'] ?? '[]', true) ?: [];
        } catch (\Exception $e) {
            // در صورت خطا، مقادیر پیش‌فرض را قرار می‌دهیم
            $this->channels = [];
            $this->forced_to_join = false;
            error_log("Error in OptionController: " . $e->getMessage());
        }
    }
    
    /**
     * ایجاد جدول options در صورت عدم وجود
     */
    private function createOptionsTable() {
        DB::rawQuery("
            CREATE TABLE IF NOT EXISTS options (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                value TEXT,
                channels TEXT,
                forced_to_join TEXT DEFAULT '[]',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP
            )
        ");
        
        // اضافه کردن مقادیر پیش‌فرض
        DB::rawQuery("
            INSERT INTO options (name, value, channels, forced_to_join) 
            VALUES 
                ('welcome_message', 'به ربات خوش آمدید!', NULL, '[]'),
                ('max_daily_coins', '10', NULL, '[]'),
                ('referral_reward', '5', NULL, '[]'),
                ('min_withdrawal', '50', NULL, '[]'),
                ('maintenance_mode', 'false', NULL, '[]'),
                ('channels', '[]', '[]', '[]')
            ON CONFLICT (name) DO NOTHING
        ");
    }
}