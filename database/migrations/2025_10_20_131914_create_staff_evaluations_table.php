<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('staff_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('sales_score')->default(0);
            $table->integer('attendance_score')->default(0);
            $table->integer('attitude_score')->default(0);
            $table->integer('total_score')->default(0);
            $table->date('evaluation_date');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('staff_evaluations');
    }
};
