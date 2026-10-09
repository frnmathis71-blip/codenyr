<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('catalog_key')->nullable()->unique();
            $table->string('pricing_mode')->default('fixed');
            $table->json('compatible_offers')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
        });
        DB::table('services')->where('price_key', 'like', 'offer_%')->orWhere('price_key', 'maintenance_business')->update(['pricing_mode' => 'starting']);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['catalog_key', 'pricing_mode', 'compatible_offers', 'archived_at']);
        });
    }
};
