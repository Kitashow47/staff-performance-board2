<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Services\SmaregiService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StaffRanking extends Component
{
    public array $staffSales = [];   // 画面表示用データ
    public string $period = 'month'; // 現状は月次固定

    public function mount(SmaregiService $smaregi)
    {
        $userId = Auth::id();

        // 📅 今月の期間を取得
        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        // ✅ API仕様：最大31日間までしか取得できない
        if ($end->diffInDays($start) > 30) {
            $end = (clone $start)->addDays(30);
        }

        // ✅ ISO8601形式に変換（Tと+09:00付き）
        $from = $start->toIso8601String();
        $to   = $end->toIso8601String();

        // 🔄 ページング対応
        $page = 1;
        $perPage = 200;
        $all = [];

        do {
            $res = $smaregi->fetchTransactions($userId, $from, $to, $page, $perPage);
            $items = is_array($res) ? $res : [];
            $all = array_merge($all, $items);

            $hasMore = count($items) === $perPage;
            $page++;
        } while ($hasMore && $page <= 10); // 無限ループ防止

        // 👥 スタッフ辞書
        $staffs = $smaregi->getStaffs($userId);
        $staffList = $staffs['data'] ?? (is_array($staffs) ? $staffs : []);

        $staffMap = collect($staffList)->mapWithKeys(function ($staff) {
            $id = $staff['staffId'] ?? null;
            $name = $staff['staffName'] ?? '不明';
            return $id ? [$id => $name] : [];
        })->all();

        // 集計用
        $agg = [];

        foreach ($all as $i => $tx) {
            $staffId =
                $tx['staffId'] ??
                ($tx['staff']['id'] ?? null) ??
                ($tx['registerStaffId'] ?? null) ??
                ($tx['cashierStaffId'] ?? null) ??
                null;

            // staffIdが存在しない場合はスキップ（null対応）
            if (empty($staffId)) {
                $staffKey = 'unknown';
            } else {
                $staffKey = (string)$staffId;
            }

            $amount =
                ($tx['totalAmount'] ?? null) ??
                ($tx['transactionTotal']['totalAmount'] ?? null) ??
                ($tx['total'] ?? null) ??
                ($tx['grandTotal'] ?? null) ??
                ($tx['amount'] ?? null) ??
                ($tx['sum'] ?? null) ??
                0;

            $amount = is_numeric($amount) ? (int)$amount : 0;

            if (!isset($agg[$staffKey])) {
                $name =
                    ($staffId && isset($staffMap[$staffKey])) ? $staffMap[$staffKey]
                    : ($tx['staffName'] ?? ($tx['staff']['name'] ?? '不明'));

                $agg[$staffKey] = [
                    'id'        => $staffId ?? 0, // ← ここを追加！
                    'staffId'   => $staffId,
                    'name'      => $name ?: '不明',
                    'amount'    => 0,
                    'count'     => 0,
                ];
            }

            $agg[$staffKey]['amount'] += $amount;
            $agg[$staffKey]['count']  += 1;

            // デバッグ用ログ（最初の5件のみ）
            if ($i < 5) {
                Log::info('[StaffRanking] TX sample', [
                    'staffId' => $staffId,
                    'pickedAmount' => $amount,
                    'rawKeys' => array_keys((array)$tx),
                ]);
            }
        }

        // 売上降順に並び替え
        $staffSales = array_values($agg);
        usort($staffSales, fn($a, $b) => $b['amount'] <=> $a['amount']);

        $this->staffSales = $staffSales;
    }

    public function render()
    {
        return view('livewire.pages.staff-ranking')
            ->layout('layouts.app');
    }
}
