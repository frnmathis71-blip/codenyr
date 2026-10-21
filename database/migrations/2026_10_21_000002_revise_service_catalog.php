<?php

use App\Models\Service;
use App\Services\CommercialCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->text('ai_notes')->nullable()->after('description');
        });
        // Leaner catalogue: merged options are archived, kept ones get clearer wording and assistant notes.
        app(CommercialCatalog::class)->revise();
        foreach (Service::where('price_key', 'like', 'offer_%')->whereNotNull('contract_rules')->get() as $offer) {
            $rules = $offer->contract_rules;
            unset($rules['exclusions']['gallery'], $rules['exclusions']['blog']);
            $offer->update(['contract_rules' => $rules]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('ai_notes');
        });
    }
};
