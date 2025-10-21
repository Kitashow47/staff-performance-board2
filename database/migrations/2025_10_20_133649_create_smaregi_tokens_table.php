<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 実行時にテーブルを作成
     */
    public function up(): void
    {
        Schema::create('smaregi_tokens', function (Blueprint $table) {
            $table->id();

            // ログイン中のユーザーIDと関連付け
            $table->unsignedBigInteger('user_id')->unique()
                  ->comment('ユーザーID（auth_users.id と対応）');

            // JWT形式のトークンは非常に長いため text 型を使用
            $table->text('access_token')
                  ->comment('スマレジアクセストークン');

            // トークン有効期限（秒数）
            $table->integer('expires_in')->nullable()
                  ->comment('トークン有効期限（秒）');

            // 有効期限の日時
            $table->timestamp('access_token_expires_at')->nullable()
                  ->comment('トークン有効期限日時');

            // スコープ（例：pos.staffs:read pos.transactions:read）
            $table->string('scope', 255)->nullable()
                  ->comment('トークンスコープ');

            // Bearerなどのトークンタイプ
            $table->string('token_type', 50)->nullable()
                  ->comment('トークンタイプ');

            // 作成・更新日時
            $table->timestamps();
        });
    }

    /**
     * ロールバック時にテーブルを削除
     */
    public function down(): void
    {
        Schema::dropIfExists('smaregi_tokens');
    }
};
