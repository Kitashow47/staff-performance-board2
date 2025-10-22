<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\SmaregiService;

class SmaregiController extends Controller
{
    protected $smaregiService;

    /** コンストラクタ */
    public function __construct(SmaregiService $smaregiService)
    {
        $this->smaregiService = $smaregiService;
    }

    /** ✅ スマレジ連携（トークン取得） */
    public function connect()
    {
        try {
            $userId = Auth::id();

            // トークン取得
            $accessToken = $this->smaregiService->fetchToken($userId);

            Log::info('Smaregi token fetched successfully', ['token' => $accessToken]);

            return redirect()->route('dashboard')->with('status', 'スマレジ連携が完了しました！');
        } catch (\Exception $e) {
            Log::error('Smaregi connect error', ['message' => $e->getMessage()]);
            return redirect()->route('dashboard')->with('error', 'スマレジ連携に失敗しました。');
        }
    }

    /** 👥 スタッフ一覧表示 */
    public function staffs()
    {
        try {
            $userId = Auth::id();

            // スマレジAPI呼び出し
            $staffs = $this->smaregiService->getStaffs($userId);

            Log::info('Smaregi Staffs API Response', $staffs);

            // レスポンスが 'data' キーでラップされている場合と、配列が直接返る場合の両方に対応
            $staffList = $staffs['data'] ?? $staffs ?? [];

            // スタッフが空ならメッセージを表示
            if (empty($staffList) || !is_array($staffList)) {
                return view('staffs', ['staffs' => [], 'message' => 'スタッフデータが見つかりませんでした。']);
            }

            // ビューへデータ送信
            return view('staffs', ['staffs' => $staffList]);
        } catch (\Exception $e) {
            Log::error('Smaregi Staffs Error', ['message' => $e->getMessage()]);
            return back()->with('error', 'スタッフ情報の取得に失敗しました。');
        }
    }

    /** 💰 取引データ取得（今後拡張予定） */
    public function transactions()
    {
        try {
            $userId = Auth::id();
            $count  = $this->smaregiService->fetchTransactions($userId);

            return redirect()->route('dashboard')->with('status', "取引データを {$count} 件取得しました。");
        } catch (\Exception $e) {
            Log::error('Smaregi Transactions Error', ['message' => $e->getMessage()]);
            return redirect()->route('dashboard')->with('error', '取引データの取得に失敗しました。');
        }
    }

}
