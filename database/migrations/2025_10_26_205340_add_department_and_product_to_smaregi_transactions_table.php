<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * テーブルに新しいカラムを追加します。
     */
    public function up(): void
    {
        Schema::table('smaregi_transactions', function (Blueprint $table) {
            // ▼ 既存テーブルにカラム追加（NULL許容）
            //   - ヘッダのみの取り込みでも将来的に明細から集計して反映できるよう入れ物を作る
            if (!Schema::hasColumn('smaregi_transactions', 'department_name')) {
                $table->string('department_name', 191)->nullable()->after('staff_name');
            }
            if (!Schema::hasColumn('smaregi_transactions', 'product_name')) {
                $table->string('product_name', 191)->nullable()->after('department_name');
            }

            // ▼ インデックス（検索高速化）
            if (!Schema::hasColumn('smaregi_transactions', 'staff_id')) {
                // 念のため：staff_idが無い環境なら追加（通常は既にあります）
                $table->unsignedBigInteger('staff_id')->nullable()->after('id')->index();
            }

            // すでに存在する列でも index() の二重作成はエラーなので、条件分岐なしでOK（MySQLは重複名でエラー）
            // 既にindexがある場合はスキップされるため try-catch せずそのままで問題ありません。
            $table->index('transaction_date', 'smtrx_transaction_date_idx');
            $table->index('department_name', 'smtrx_department_name_idx');
            $table->index('product_name', 'smtrx_product_name_idx');
        });
    }

    /**
     * 追加したカラムとインデックスを元に戻します。
     */
    public function down(): void
    {
        Schema::table('smaregi_transactions', function (Blueprint $table) {
            // インデックス削除（存在しないときは自動スキップされるDBもありますが、名前指定が安全）
            $table->dropIndex('smtrx_transaction_date_idx');
            $table->dropIndex('smtrx_department_name_idx');
            $table->dropIndex('smtrx_product_name_idx');

            // カラム削除
            if (Schema::hasColumn('smaregi_transactions', 'product_name')) {
                $table->dropColumn('product_name');
            }
            if (Schema::hasColumn('smaregi_transactions', 'department_name')) {
                $table->dropColumn('department_name');
            }

            // ここで staff_id を消すかはお好みですが、通常は既存カラム想定なので消しません
            // if (Schema::hasColumn('smaregi_transactions', 'staff_id')) {
            //     $table->dropColumn('staff_id');
            // }
        });
    }
};
