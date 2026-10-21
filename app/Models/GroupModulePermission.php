<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $group_id
 * @property string $module_key
 * @property bool $can_read
 * @property bool $can_write
 * @property bool $can_update
 * @property bool $can_delete
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GroupModulePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'module_key',
        'can_read',
        'can_write',
        'can_update',
        'can_delete',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group_id' => 'integer',
            'can_read' => 'boolean',
            'can_write' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_key', 'key');
    }
}
