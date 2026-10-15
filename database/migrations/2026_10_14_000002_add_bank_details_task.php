<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_tasks')->insert([
            'category' => 'Administratif',
            'title' => 'Renseigner l’IBAN sur les factures',
            'details' => 'Une fois le compte bancaire ouvert, ajoutez CODENYR_BANK_IBAN et CODENYR_BANK_BIC dans le .env de production : les factures afficheront un encadré « Règlement par virement ».',
            'position' => (int) DB::table('admin_tasks')->max('position') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('admin_tasks')->where('title', 'Renseigner l’IBAN sur les factures')->delete();
    }
};
