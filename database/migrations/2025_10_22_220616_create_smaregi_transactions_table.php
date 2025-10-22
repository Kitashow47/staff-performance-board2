<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('smaregi_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_head_id')->unique();
            $table->string('customer_name')->nullable();
            $table->string('staff_name')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('payment_type')->nullable(); // この行を追加
            $table->timestamp('transaction_date')->nullable();
            $table->json('raw_data')->nullable(); // APIレスポンス全体を保存
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smaregi_transactions');
    }
};
