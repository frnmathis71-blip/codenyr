<?php

use App\Models\Price;
use App\Models\Service;
use App\Services\CommercialCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Two maintenance plans: Essentiel and Sérénité (formerly Pro, same key so contracts keep working).
        Price::whereKey('maintenance_pro')->update(['amount_cents' => 4490]);
        Service::where('price_key', 'maintenance_pro')->update(['name' => 'Sérénité', 'description' => config('codenyr.maintenance.pro.intro')]);
        // Archived, never deleted: old quotes keep their Business line.
        Service::where('price_key', 'maintenance_business')->whereNull('archived_at')->update(['active' => false, 'archived_at' => now()]);
        app(CommercialCatalog::class)->revise();
    }

    public function down(): void
    {
        Service::where('price_key', 'maintenance_business')->update(['active' => true, 'archived_at' => null]);
        Service::where('price_key', 'maintenance_pro')->update(['name' => 'Pro']);
    }
};
