<?php

declare(strict_types=1);

namespace App\Domain\SafeMode\Enums;

enum DestructiveAction: string
{
    case SystemReboot = 'system_reboot';
    case SystemShutdown = 'system_shutdown';
    case ResetConfiguration = 'reset_configuration';
    case PackageUpgrade = 'package_upgrade';
    case BulkDeleteVouchers = 'bulk_delete_vouchers';
    case BulkDeleteProfiles = 'bulk_delete_profiles';
    case BulkDeletePppSecrets = 'bulk_delete_ppp_secrets';
    case BulkDeletePppProfiles = 'bulk_delete_ppp_profiles';
    case FirewallModification = 'firewall_modification';
    case ScriptExecution = 'script_execution';
    case RouterDeletion = 'router_deletion';

    public function description(): string
    {
        return match ($this) {
            self::SystemReboot => 'Rebooting the MikroTik router hardware.',
            self::SystemShutdown => 'Shutting down the MikroTik router.',
            self::ResetConfiguration => 'Resetting router configuration to factory defaults.',
            self::PackageUpgrade => 'Upgrading or altering RouterOS firmware packages.',
            self::BulkDeleteVouchers => 'Mass removal of hotspot vouchers/users.',
            self::BulkDeleteProfiles => 'Mass removal of hotspot user profiles.',
            self::BulkDeletePppSecrets => 'Removal or deletion of dedicated PPP secrets.',
            self::BulkDeletePppProfiles => 'Removal or deletion of PPP bandwidth profiles.',
            self::FirewallModification => 'Deleting or mutating core firewall filter/NAT rules.',
            self::ScriptExecution => 'Running custom automated terminal scripts altering routing or interfaces.',
            self::RouterDeletion => 'Deleting router registration and all associated database records.',
        };
    }
}
