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
        if (Schema::hasTable('dedicated_users')) {
            return;
        }

        Schema::create('dedicated_users', function (Blueprint $table): void {
            $table->string('id', 36)->primary(); // ULID for anti-tamper security
            $table->foreignId('router_id')->constrained('routers')->cascadeOnDelete();
            $table->foreignId('ppp_profile_id')->nullable()->constrained('ppp_profiles')->nullOnDelete();
            $table->string('name', 100); // RouterOS secret username
            $table->string('password', 100); // RouterOS secret password
            $table->string('service', 20)->default('pppoe'); // pppoe, any, l2tp, sstp, pptp, ovpn
            $table->string('profile', 100)->default('default');
            $table->string('local_address', 50)->nullable();
            $table->string('remote_address', 50)->nullable(); // Dedicated static IP or pool
            $table->string('caller_id', 50)->nullable(); // MAC address binding
            $table->string('routes', 255)->nullable();
            $table->string('customer_name', 150)->nullable(); // Customer or company name
            $table->string('customer_phone', 50)->nullable();
            $table->text('customer_address')->nullable();
            $table->decimal('monthly_price', 12, 2)->default(0.00);
            $table->string('billing_cycle', 30)->default('monthly');
            $table->string('comment', 255)->nullable();
            $table->boolean('disabled')->default(false);
            $table->boolean('synced_to_router')->default(false);
            $table->text('sync_failed_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['router_id', 'name']);
            $table->index('router_id');
            $table->index('customer_name');
            $table->index('remote_address');
            $table->index('disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dedicated_users');
    }
};
