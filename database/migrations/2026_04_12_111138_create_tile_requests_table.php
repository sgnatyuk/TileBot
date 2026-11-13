<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tile_requests', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_user_id')->index();
            $table->string('telegram_username')->nullable();
            $table->string('file_id');        // Telegram file_id для отправки превью
            $table->string('description');    // Краткое описание для истории (бренд + коллекция)
            $table->text('full_result');      // Полный ответ от Gemini
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tile_requests');
    }
};
