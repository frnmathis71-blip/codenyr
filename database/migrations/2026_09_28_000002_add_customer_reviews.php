<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
        });
        Schema::table('testimonials', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('moderation_status')->default('draft')->index();
            $table->text('moderation_note')->nullable();
            $table->unsignedInteger('revision')->default(1);
        });
        DB::table('testimonials')->where('published', true)->update(['moderation_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropForeign(['lead_id']);
            $table->dropUnique(['lead_id']);
            $table->dropColumn('lead_id');
            $table->dropColumn(['moderation_status', 'moderation_note', 'revision']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('delivered_at');
        });
    }
};
