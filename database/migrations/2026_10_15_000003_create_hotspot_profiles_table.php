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
        if (Schema::hasTable('hotspot_profiles')) {
            return;
        }

        Schema::create('hotspot_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('shared_users')->default(1);
            $table->string('rate_limit', 50)->nullable();
            $table->string('validity_duration', 50)->nullable();
            $table->string('validity_unit', 20)->nullable();
            $table->decimal('price', 12, 2)->default(0.00);
            $table->decimal('selling_price', 12, 2)->default(0.00);
            $table->text('on_login_script')->nullable();
            $table->text('on_logout_script')->nullable();
            $table->string('address_pool', 100)->nullable();
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
        Schema::dropIfExists('hotspot_profiles');
    }
};
