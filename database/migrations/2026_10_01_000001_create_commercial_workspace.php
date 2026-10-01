<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('registration')->nullable();
            $table->timestamps();
        });
        Schema::create('client_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->string('status')->default('quote_to_prepare')->index();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->string('website_url')->nullable();
            $table->string('domain')->nullable();
            $table->string('host')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('price_key')->nullable()->unique();
            $table->string('unit')->default('forfait');
            $table->string('frequency')->default('once');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('commercial_settings', function (Blueprint $table) {
            $table->id();
            $table->json('values');
            $table->timestamps();
        });
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('value')->default(0);
            $table->unique(['kind', 'year']);
        });
        foreach (['quotes', 'invoices'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->foreignId('client_project_id')->constrained()->restrictOnDelete();
                if ($name === 'invoices') {
                    $table->foreignId('quote_id')->nullable()->constrained()->restrictOnDelete();
                    $table->string('invoice_type')->default('standard');
                }
                $table->string('number')->nullable()->unique();
                $table->string('status')->default('draft')->index();
                $table->date('issued_on');
                $table->date('due_on');
                $table->json('items');
                $table->json('snapshot');
                $table->json('totals');
                $table->string('discount_type')->default('percent');
                $table->unsignedBigInteger('discount_value')->default(0);
                $table->string('deposit_type')->default('percent');
                $table->unsignedBigInteger('deposit_value')->default(0);
                $table->text('conditions')->nullable();
                $table->text('estimated_delay')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->text('comment')->nullable();
            $table->uuid('submission_key')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('original_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('available');
            $table->date('document_date');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->longText('content')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->unique(['document_id', 'version']);
        });
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->longText('content');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('maintenance_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->string('frequency');
            $table->date('starts_on');
            $table->unsignedInteger('duration_months')->default(12);
            $table->string('renewal')->nullable();
            $table->text('included')->nullable();
            $table->string('response_time')->nullable();
            $table->text('termination')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
        Schema::create('project_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
        });
        Schema::create('project_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['project_events', 'project_notes', 'maintenance_contracts', 'document_templates', 'document_versions', 'documents', 'payments', 'invoices', 'quotes', 'document_sequences', 'commercial_settings', 'services', 'client_projects', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
