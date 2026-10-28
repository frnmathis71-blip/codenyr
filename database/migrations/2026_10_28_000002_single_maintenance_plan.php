<?php

use App\Models\Price;
use App\Models\Service;
use App\Services\CommercialCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // One maintenance plan left: Essentiel at 29,99 €. Sérénité is archived, never deleted: old quotes keep their line.
        Price::whereKey('maintenance_essential')->update(['amount_cents' => 2999]);
        Service::where('price_key', 'maintenance_essential')->update(['description' => config('codenyr.maintenance.essential.intro')]);
        Service::where('price_key', 'maintenance_pro')->whereNull('archived_at')->update(['active' => false, 'archived_at' => now()]);
        app(CommercialCatalog::class)->revise();
    }

    public function down(): void
    {
        Service::where('price_key', 'maintenance_pro')->update(['active' => true, 'archived_at' => null]);
    }
};
