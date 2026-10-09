<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['firstname', 'lastname', 'siren', 'siret', 'postal_code', 'city', 'address_extra', 'billing_name', 'billing_email'] as $field) {
                $table->string($field)->nullable();
            }
            $table->text('billing_address')->nullable();
        });
        Schema::table('document_templates', function (Blueprint $table) {
            $table->string('status')->default('draft')->index();
            $table->string('version_label')->nullable();
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->string('number')->nullable()->unique();
            $table->unsignedSmallInteger('engine_version')->default(1);
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('cgv_version_id')->nullable()->constrained('document_templates')->restrictOnDelete();
            $table->foreignId('parent_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('maintenance_contract_id')->nullable()->constrained('maintenance_contracts')->restrictOnDelete();
            $table->string('relationship')->nullable();
        });
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('parent_quote_id')->nullable()->constrained('quotes')->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
        });
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->text('exclusions')->nullable();
            $table->text('backups')->nullable();
            $table->text('security')->nullable();
        });
        // Existing legal versions remain usable by explicit selection, never activated implicitly.
        DB::table('document_templates')->orderBy('id')->eachById(function ($template) {
            DB::table('document_templates')->where('id', $template->id)->update(['version_label' => (string) $template->revision]);
        });
        DB::table('documents')->whereNotNull('snapshot')->orderBy('id')->eachById(function ($document) {
            $snapshot = json_decode($document->snapshot, true) ?? [];
            $input = json_decode($document->contract_data ?? '{}', true) ?? [];
            $templateId = $input['terms_template_id'] ?? $snapshot['terms']['id'] ?? null;
            $parentId = $snapshot['previous_document_id'] ?? null;
            DB::table('documents')->where('id', $document->id)->update([
                'revision' => max(1, (int) ($snapshot['revision'] ?? 1)),
                'cgv_version_id' => $templateId && DB::table('document_templates')->where('id', $templateId)->exists() ? $templateId : null,
                'parent_document_id' => $parentId && DB::table('documents')->where('id', $parentId)->exists() ? $parentId : null,
                'relationship' => $parentId ? 'revision' : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            foreach (['cgv_version_id', 'parent_document_id', 'maintenance_contract_id'] as $field) {
                $table->dropConstrainedForeignId($field);
            }
            $table->dropColumn(['number', 'engine_version', 'revision', 'relationship']);
        });
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_quote_id');
            $table->dropColumn('revision');
        });
        Schema::table('document_templates', fn (Blueprint $table) => $table->dropColumn(['status', 'version_label']));
        Schema::table('maintenance_contracts', fn (Blueprint $table) => $table->dropColumn(['exclusions', 'backups', 'security']));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['firstname', 'lastname', 'siren', 'siret', 'postal_code', 'city', 'address_extra', 'billing_name', 'billing_email', 'billing_address']));
    }
};
