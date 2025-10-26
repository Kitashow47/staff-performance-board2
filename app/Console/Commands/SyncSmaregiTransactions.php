<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use App\Services\SmaregiService;

class SyncSmaregiTransactions extends Command
{
    /**
     * コマンド名（artisanで呼び出すときの名前）
     */
    protected $signature = 'smaregi:sync-transactions';

    /**
     * コマンドの説明
     */
    protected $description = 'スマレジAPIから取引データ（詳細付き）を取得し、DBへ同期する';

    /**
     * メイン処理
     */
    public function handle(SmaregiService $smaregi)
    {
        $this->info('📡 スマレジ取引データ同期を開始します...');

        try {
            // 開発用に user_id = 1 を固定
            $userId = 1;

            $from = now()->startOfMonth()->toIso8601String();
            $to   = now()->endOfMonth()->toIso8601String();

            $transactions = $smaregi->fetchTransactions($userId, $from, $to);

            $count = count($transactions);
            $this->info("✅ 同期が完了しました：{$count}件の取引を更新しました。");
        } catch (\Exception $e) {
            $this->error('❌ 同期中にエラーが発生しました: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
