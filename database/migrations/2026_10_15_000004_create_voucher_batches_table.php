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
        if (Schema::hasTable('voucher_batches')) {
            return;
        }

        Schema::create('voucher_batches', function (Blueprint $table): void {
            $table->string('id', 36)->primary(); // UUID or ULID
            $table->foreignId('router_id')->constrained('routers')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('hotspot_profiles')->restrictOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('prefix', 20)->nullable();
            $table->unsignedSmallInteger('character_length')->default(6);
            $table->string('code_format', 30)->default('alphanumeric');
            $table->string('password_mode', 20)->default('same_as_user');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('router_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_batches');
    }
};
