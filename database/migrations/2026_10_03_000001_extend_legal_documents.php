<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['audience', 'company', 'country', 'vat_number', 'signatory', 'signatory_role'] as $field) {
                $table->string($field)->nullable();
            }
        });
        Schema::table('client_projects', fn (Blueprint $table) => $table->json('contract_data')->nullable());
        Schema::table('documents', function (Blueprint $table) {
            $table->json('contract_data')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestamp('signed_imported_at')->nullable();
            $table->foreignId('signed_imported_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('document_templates', function (Blueprint $table) {
            $table->date('effective_on')->nullable();
            $table->json('parameters')->nullable();
            $table->foreignId('previous_id')->nullable()->constrained('document_templates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_id');
            $table->dropColumn(['effective_on', 'parameters']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropConstrainedForeignId('signed_imported_by');
            $table->dropColumn(['contract_data', 'finalized_at', 'pdf_sha256', 'signed_imported_at']);
        });
        Schema::table('client_projects', fn (Blueprint $table) => $table->dropColumn('contract_data'));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['audience', 'company', 'country', 'vat_number', 'signatory', 'signatory_role']));
    }
};
