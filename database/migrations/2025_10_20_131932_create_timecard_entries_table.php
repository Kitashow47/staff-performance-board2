<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('timecard_entries', function (Blueprint $table) {
            $table->id();
            $table->string('smaregi_id')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->datetime('recorded_at');
            $table->enum('type', ['in', 'out']);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('timecard_entries');
    }
};
