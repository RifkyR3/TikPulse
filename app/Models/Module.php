<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $key
 * @property string|null $description
 * @property array<string> $available_actions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'description',
        'available_actions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_actions' => 'array',
        ];
    }

    /**
     * @return HasMany<GroupModulePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(GroupModulePermission::class, 'module_key', 'key');
    }
}
