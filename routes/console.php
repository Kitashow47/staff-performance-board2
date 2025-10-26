<?php

use Illuminate\Support\Facades\DB;

Artisan::command('smaregi:check-db', function () {
    $count = DB::table('smaregi_transactions')->count();
    $latest = DB::table('smaregi_transactions')
        ->orderByDesc('transaction_date')
        ->limit(3)
        ->get();

    $this->info("✅ 登録件数: {$count}");
    $this->info("📦 最新3件:");
    $this->line(json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
});
