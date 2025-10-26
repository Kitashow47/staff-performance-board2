<?php

use App\Http\Controllers\SmaregiController;
use Illuminate\Support\Facades\Route;
use App\Livewire\StaffRanking; // ← ✅ ここがポイント！パスが違います

// ログイン後のみアクセス可能
Route::middleware(['auth'])->group(function () {
    // ダッシュボード
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // ✅ スマレジ連携関連ルート
    Route::get('/smaregi/connect', [SmaregiController::class, 'connect'])->name('smaregi.connect');
    Route::get('/smaregi/staffs', [SmaregiController::class, 'staffs'])->name('smaregi.staffs');
    Route::get('/smaregi/transactions', [SmaregiController::class, 'transactions'])->name('smaregi.transactions');

    // ✅ Livewire v3 対応：スタッフランキング
    Route::get('/ranking', StaffRanking::class)
        ->middleware('auth')
        ->name('ranking');
});

// ✅ 認証機能（ログイン・ログアウト）
require __DIR__.'/auth.php';

// ✅ プロフィール編集ルート（Breeze / Jetstream 用）
// require __DIR__.'/profile.php';
