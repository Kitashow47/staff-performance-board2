<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmaregiTransaction;

class UpdateTransactionDetails extends Command
{
    protected $signature = 'smaregi:update-transactions';
    protected $description = 'Parse raw_data JSON and fill department_name, product_name, and total_amount in smaregi_transactions table';

    public function handle()
    {
        $transactions = SmaregiTransaction::whereNotNull('raw_data')->get();
        $updatedCount = 0;

        foreach ($transactions as $tx) {
            $raw = $tx->raw_data;

            // 🔧 raw_dataが配列 or 文字列どちらでも対応
            $data = is_array($raw) ? $raw : json_decode($raw, true);

            if (!$data || !is_array($data)) {
                continue;
            }

            // 取引データから必要項目を抽出
            $total = $data['total'] ?? $data['totalAmount'] ?? $data['grandTotal'] ?? 0;
            $department = $data['departmentName'] ?? ($data['items'][0]['departmentName'] ?? null);
            $product = $data['productName'] ?? ($data['items'][0]['productName'] ?? null);

            // 更新処理（既存値が空の場合のみ上書き）
            if (empty($tx->total_amount) && $total) {
                $tx->total_amount = $total;
            }

            if (empty($tx->department_name) && $department) {
                $tx->department_name = $department;
            }

            if (empty($tx->product_name) && $product) {
                $tx->product_name = $product;
            }

            if ($tx->isDirty()) {
                $tx->save();
                $updatedCount++;
            }
        }

        $this->info("✅ {$updatedCount}件の取引を更新しました。");
        return Command::SUCCESS;
    }
}
