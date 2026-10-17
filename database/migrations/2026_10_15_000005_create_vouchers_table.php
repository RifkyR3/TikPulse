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
        if (Schema::hasTable('vouchers')) {
            return;
        }

        Schema::create('vouchers', function (Blueprint $table): void {
            $table->string('id', 36)->primary(); // UUID or ULID
            $table->string('batch_id', 36)->nullable();
            $table->foreignId('router_id')->constrained('routers')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('hotspot_profiles')->restrictOnDelete();
            $table->string('username', 64);
            $table->string('password', 64);
            $table->string('time_limit', 50)->nullable();
            $table->unsignedBigInteger('data_limit_bytes')->nullable();
            $table->string('comment', 150)->nullable();
            $table->string('status', 20)->default('available')->index(); // available, active, expired, disabled
            $table->boolean('is_printed')->default(false)->index();
            $table->timestampTz('printed_at')->nullable();
            $table->timestampTz('first_login_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->string('mac_address', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('synced_to_router')->default(false);
            $table->text('sync_failed_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('batch_id')->references('id')->on('voucher_batches')->nullOnDelete();
            $table->unique(['router_id', 'username']);
            $table->index(['router_id', 'status']);
            $table->index('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
