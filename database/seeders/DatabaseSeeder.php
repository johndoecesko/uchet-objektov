<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $cats = [
            ['Материалы и оборудование', false],
            ['Работа (зарплата из журнала)', true],
            ['Субподряд', false],
            ['Транспорт и ГСМ', false],
            ['Аренда техники и инструмента', false],
            ['Проживание и командировки', false],
            ['Инструмент и расходники', false],
            ['Прочее', false],
        ];

        foreach ($cats as $i => [$name, $labor]) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_labor' => $labor, 'sort' => ($i + 1) * 10, 'is_active' => true]);
        }

        if (! ExpenseCategory::where('is_tax', true)->exists()) {
            ExpenseCategory::create(['name' => 'Налоги (% от договора)', 'is_tax' => true, 'sort' => 85, 'is_active' => true]);
        }
    }
}
