<?php

use App\Http\Controllers\SmaregiController;
use Illuminate\Support\Facades\Route;
use App\Livewire\StaffRanking;
use App\Livewire\StaffDetail;

// ================================
// 🔐 ログイン後のみアクセス可能
// ================================
Route::middleware(['auth'])->group(function () {

    // ✅ ダッシュボード
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // ✅ スマレジ連携関連ルート
    Route::get('/smaregi/connect', [SmaregiController::class, 'connect'])->name('smaregi.connect');
    Route::get('/smaregi/staffs', [SmaregiController::class, 'staffs'])->name('smaregi.staffs');
    Route::get('/smaregi/transactions', [SmaregiController::class, 'transactions'])->name('smaregi.transactions');

    // ✅ スタッフランキング（Livewire v3対応）
    Route::get('/ranking', StaffRanking::class)
        ->name('ranking');

    // ✅ スタッフ詳細ページ（Livewire v3対応）
    Route::get('/staff/{id}', StaffDetail::class)
        ->name('staff.detail');
});

// ================================
// 🔑 認証機能（Breeze / Jetstream）
// ================================
require __DIR__ . '/auth.php';

// ✅ プロフィール編集ルート（Breeze / Jetstream 用）
// require __DIR__ . '/profile.php';
