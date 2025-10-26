<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmaregiTransaction extends Model
{
    use HasFactory;

    protected $table = 'smaregi_transactions'; // 対応するテーブル名

    // 🔹 ここに追加したいカラムを列挙
    protected $fillable = [
        'staff_id',
        'staff_name',
        'transaction_date',
        'total_amount',
        'department_name',   // ← 今回追加したカラム
        'product_name',      // ← 今回追加したカラム
        'raw_data',          // JSONデータ
    ];

    // 🔹 キャスト設定（自動変換）
    protected $casts = [
        'transaction_date' => 'datetime',
        'total_amount' => 'integer',
        'raw_data' => 'array',
    ];
}
