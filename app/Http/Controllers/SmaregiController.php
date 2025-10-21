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

            // デバッグ用ログ
            Log::info('Smaregi Staffs API Response', $staffs);

            // 返却形式によって分岐
            if (isset($staffs['data'])) {
                $staffList = $staffs['data'];
            } elseif (is_array($staffs)) {
                $staffList = $staffs;
            } else {
                $staffList = [];
            }

            // スタッフが空ならメッセージを表示
            if (empty($staffList)) {
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
            $transactions = $this->smaregiService->getTransactions($userId);

            return view('transactions', ['transactions' => $transactions]);
        } catch (\Exception $e) {
            Log::error('Smaregi Transactions Error', ['message' => $e->getMessage()]);
            return back()->with('error', '取引情報の取得に失敗しました。');
        }
    }
}
