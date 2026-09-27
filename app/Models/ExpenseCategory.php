<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'is_labor', 'is_tax', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_labor' => 'boolean', 'is_tax' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public static function taxId(): ?int
    {
        return static::query()->where('is_tax', true)->orderBy('sort')->value('id');
    }

    public static function laborId(): ?int
    {
        return static::query()->where('is_labor', true)->orderBy('sort')->value('id');
    }
}
