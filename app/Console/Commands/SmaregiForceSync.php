<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\SmaregiService;
use Carbon\Carbon;

class SmaregiForceSync extends Command
{
    protected $signature = 'smaregi:force-sync';
    protected $description = 'スマレジ取引を with_details=all で強制再取得し、DBを上書き更新する';

    public function handle(SmaregiService $smaregi)
    {
        $this->info('📡 Smaregi Force Sync 開始（with_details=all）...');
        $userId = DB::table('users')->value('id'); // 最初のユーザーを仮定

        $from = Carbon::now()->startOfMonth()->toIso8601String();
        $to   = Carbon::now()->endOfMonth()->toIso8601String();

        try {
            // ✅ limit=100 以下で実行（Smaregi制約対応）
            $transactions = $smaregi->fetchTransactions(
                $userId,
                $from,
                $to,
                1,
                100,   // ← 🔥 修正ポイント
                true   // ← with_details=all
            );

            $this->info('✅ 取得完了: ' . count($transactions) . ' 件');
        } catch (\Throwable $e) {
            $this->error('❌ エラー: ' . $e->getMessage());
        }

        return 0;
    }
}
