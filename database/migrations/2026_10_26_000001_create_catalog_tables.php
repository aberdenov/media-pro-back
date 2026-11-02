<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->string('external_id');
            $table->string('article')->index();
            $table->string('name');
            $table->text('search_text');
            $table->text('description')->nullable();
            $table->string('category', 64)->index();
            $table->string('brand')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('currency', 3)->default('KZT');
            $table->unsignedInteger('stock')->nullable();
            $table->json('images');
            $table->json('attributes');
            $table->string('source_url', 2048)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->uuid('sync_token')->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->index(['active', 'category', 'price']);
        });
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->string('status', 16)->default('running');
            $table->unsignedInteger('processed')->default(0);
            $table->string('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
        });
        Schema::create('inquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('phone', 40);
            $table->string('email')->nullable();
            $table->string('company', 200)->nullable();
            $table->text('message')->nullable();
            $table->json('items');
            $table->timestamp('consented_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('products');
    }
};
