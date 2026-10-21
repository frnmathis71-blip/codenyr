<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Which assistant the conversation belongs to (see AdminAssistant::AGENTS).
            $table->string('agent', 30)->default('admin')->index();
            // AI provider that started it (see AdminAssistant::PROVIDERS): its history only replays there.
            $table->string('provider', 30)->default('anthropic');
            $table->string('title', 200)->nullable();
            // Exact API history (append-only: thinking blocks must be replayed unchanged).
            $table->json('messages');
            // What the admin sees: their messages, the assistant's replies and the actions performed.
            $table->json('log');
            // Work runs one model call per request (web servers cut long requests): "running" until the reply is complete.
            $table->string('status', 20)->default('idle');
            $table->unsignedSmallInteger('steps')->default(0);
            // Tool calls of the current turn still to execute, and the delegated conversation being waited for.
            $table->json('pending')->nullable();
            $table->foreignId('waiting_on')->nullable()->constrained('assistant_conversations')->nullOnDelete();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_tokens')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_conversations');
    }
};
