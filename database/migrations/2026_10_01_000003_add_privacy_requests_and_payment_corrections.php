<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 1000)->nullable();
        });
        Schema::table('testimonials', function (Blueprint $table) {
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_version')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
        });
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('type');
            $table->text('message')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::table('testimonials', fn (Blueprint $table) => $table->dropColumn(['consented_at', 'consent_version', 'withdrawn_at']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['voided_at', 'void_reason']));
    }
};
