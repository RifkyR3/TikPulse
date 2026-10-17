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
        if (Schema::hasTable('safe_mode_audit_logs')) {
            return;
        }

        Schema::create('safe_mode_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('command_type', 50); // routeros_command, bulk_delete, firmware_upgrade
            $table->string('target_endpoint', 255); // /system/reboot, /ip/hotspot/user/remove
            $table->json('payload')->nullable();
            $table->boolean('is_blocked')->default(true)->index();
            $table->text('bypass_reason')->nullable();
            $table->string('client_ip', 45);
            $table->timestampTz('created_at')->useCurrent()->index();

            $table->index('router_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safe_mode_audit_logs');
    }
};
