<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\SmaregiService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StaffDetail extends Component
{
    public int $staffId;
    public string $staffName = '';
    public string $period = 'month';
    public array $salesByProduct = [];
    public array $salesByCategory = [];
    public int $totalSales = 0;
    public int $totalCount = 0;
    public int $avgUnitPrice = 0;

    public function mount($id, SmaregiService $smaregi)
    {
        $this->staffId = (int) $id;
        $this->loadData($smaregi);
    }

    public function setPeriod(string $period, SmaregiService $smaregi)
    {
        $this->period = $period;
        $this->loadData($smaregi);
    }

    private function loadData(SmaregiService $smaregi)
    {
        $userId = Auth::id();

        $now = Carbon::now();
        switch ($this->period) {
            case 'day':
                $from = $now->copy()->startOfDay();
                $to   = $now->copy()->endOfDay();
                break;
            case 'week':
                $from = $now->copy()->startOfWeek();
                $to   = $now->copy()->endOfWeek();
                break;
            default:
                $from = $now->copy()->startOfMonth();
                $to   = $now->copy()->endOfMonth();
                break;
        }

        $transactions = DB::table('smaregi_transactions')
            ->where(function ($q) {
                $q->where('staff_id', (string)$this->staffId)
                  ->orWhere('staff_id', (int)$this->staffId);
            })
            ->whereBetween('transaction_date', [$from, $to])
            ->get();

        Log::info('[StaffDetail] DB取得件数', [
            'staff_id' => $this->staffId,
            'count' => $transactions->count(),
        ]);

        if ($transactions->isEmpty()) {
            try {
                $smaregi->fetchTransactions(
                    $userId,
                    $from->toIso8601String(),
                    $to->toIso8601String(),
                    1,
                    200
                );

                $transactions = DB::table('smaregi_transactions')
                    ->where(function ($q) {
                        $q->where('staff_id', (string)$this->staffId)
                          ->orWhere('staff_id', (int)$this->staffId);
                    })
                    ->whereBetween('transaction_date', [$from, $to])
                    ->get();
            } catch (\Throwable $e) {
                Log::error('[StaffDetail] APIエラー', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->staffName = $transactions->first()->staff_name ?? '不明';

        $byProduct = [];
        $byCategory = [];
        $total = 0;
        $count = 0;

        foreach ($transactions as $t) {
            $raw = json_decode($t->raw_data, true);

            // ✅ detailsがない場合はフォールバック
            $details = $raw['details'] ?? [ [
                'productName' => $raw['productName'] ?? '不明',
                'categoryName' => $raw['categoryName'] ?? '不明',
                'salesPrice' => $raw['total'] ?? 0
            ] ];

            foreach ($details as $d) {
                $pName = $d['productName'] ?? '不明';
                $cName = $d['categoryName'] ?? '不明';
                $amount = (int) ($d['salesPrice'] ?? 0);

                if (!isset($byProduct[$pName])) {
                    $byProduct[$pName] = ['name' => $pName, 'total' => 0, 'count' => 0];
                }
                $byProduct[$pName]['total'] += $amount;
                $byProduct[$pName]['count']++;

                if (!isset($byCategory[$cName])) {
                    $byCategory[$cName] = ['name' => $cName, 'total' => 0, 'count' => 0];
                }
                $byCategory[$cName]['total'] += $amount;
                $byCategory[$cName]['count']++;

                $total += $amount;
                $count++;
            }
        }

        $this->totalSales = $total;
        $this->totalCount = $count;
        $this->avgUnitPrice = $count > 0 ? (int) ($total / $count) : 0;

        $this->salesByProduct = collect($byProduct)->sortByDesc('total')->values()->all();
        $this->salesByCategory = collect($byCategory)->sortByDesc('total')->values()->all();
    }

    public function render()
    {
        return view('livewire.staff-detail')
            ->layout('layouts.app');
    }
}
