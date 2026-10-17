<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ppp_profiles')) {
            return;
        }

        Schema::create('ppp_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('local_address', 50)->nullable();
            $table->string('remote_address', 100)->nullable();
            $table->string('rate_limit', 50)->nullable(); // e.g. 10M/20M
            $table->boolean('only_one')->default(true);
            $table->string('dns_server', 100)->nullable();
            $table->decimal('price', 12, 2)->default(0.00); // Monthly subscription price
            $table->string('comment', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['router_id', 'name']);
            $table->index('router_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppp_profiles');
    }
};
