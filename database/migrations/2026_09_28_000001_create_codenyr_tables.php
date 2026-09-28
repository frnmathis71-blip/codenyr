<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('project_type');
            $table->string('budget')->nullable();
            $table->json('features')->nullable();
            $table->text('description');
            $table->string('desired_date')->nullable();
            $table->string('website')->nullable();
            $table->string('status')->default('new')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('client');
            $table->string('category')->index();
            $table->string('short_description', 500);
            $table->text('description');
            $table->text('problem');
            $table->text('solution');
            $table->string('image')->nullable();
            $table->json('screenshots')->nullable();
            $table->text('features')->nullable();
            $table->string('website_url')->nullable();
            $table->json('technologies')->nullable();
            $table->boolean('published')->default(false)->index();
            $table->boolean('featured')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('company')->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('leads');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
