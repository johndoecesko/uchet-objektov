<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Статья затрат. Справочник свой у каждой организации */
class ExpenseCategory extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'is_labor', 'is_tax', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_labor' => 'boolean', 'is_tax' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Статья налогов организации (без $organizationId — текущей) */
    public static function taxId(?int $organizationId = null): ?int
    {
        return static::inOrganization($organizationId)->where('is_tax', true)->orderBy('sort')->value('id');
    }

    /** Статья, куда идёт зарплата из журнала работ */
    public static function laborId(?int $organizationId = null): ?int
    {
        return static::inOrganization($organizationId)->where('is_labor', true)->orderBy('sort')->value('id');
    }

    private static function inOrganization(?int $organizationId): Builder
    {
        return $organizationId
            ? static::acrossOrganizations()->where('organization_id', $organizationId)
            : static::query();
    }
}
