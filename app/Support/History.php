<?php

namespace App\Support;

use App\Filament\Resources\AdvanceResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\IncomeResource;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WorkLogResource;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkLog;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/** Человекочитаемый вид записей истории изменений */
class History
{
    public const MODELS = [
        Expense::class => 'Расход',
        WorkLog::class => 'Работа',
        Income::class => 'Поступление',
        Payout::class => 'Выплата',
        Advance::class => 'Подотчёт',
        Project::class => 'Объект',
        ProjectBudget::class => 'План по статье',
        Employee::class => 'Сотрудник',
        User::class => 'Пользователь',
        Setting::class => 'Настройка',
    ];

    public const EVENTS = [
        'created' => 'Создано',
        'updated' => 'Изменено',
        'trashed' => 'В корзину',
        'restored' => 'Восстановлено',
        'forceDeleted' => 'Удалено навсегда',
        'deleted' => 'Удалено',
    ];

    public const EVENT_COLORS = [
        'created' => 'success',
        'updated' => 'info',
        'trashed' => 'warning',
        'restored' => 'success',
        'forceDeleted' => 'danger',
        'deleted' => 'danger',
    ];

    private const FIELDS = [
        'date' => 'Дата', 'amount' => 'Сумма', 'project_id' => 'Объект', 'expense_category_id' => 'Статья',
        'employee_id' => 'Сотрудник', 'user_id' => 'Пользователь', 'supplier' => 'Поставщик', 'document' => 'Документ',
        'payment_source' => 'Чем оплачено', 'description' => 'Описание', 'attachments' => 'Чеки', 'work' => 'Работы',
        'days' => 'Дней', 'rate' => 'Ставка', 'notes' => 'Примечание', 'type' => 'Тип', 'method' => 'Способ',
        'name' => 'Название', 'customer' => 'Заказчик', 'customer_inn' => 'ИНН', 'customer_kpp' => 'КПП',
        'customer_ogrn' => 'ОГРН', 'customer_address' => 'Адрес заказчика', 'address' => 'Адрес',
        'contract_number' => '№ договора', 'contract_date' => 'Дата договора', 'contract_amount' => 'Сумма договора',
        'tax_percent' => 'Налог, %', 'status' => 'Статус', 'start_date' => 'Начало', 'end_date' => 'Окончание',
        'phone' => 'Телефон', 'position' => 'Должность', 'day_rate' => 'Ставка в день', 'card_percent' => '% на карту',
        'is_active' => 'Активен', 'email' => 'Email', 'role' => 'Роль', 'source' => 'Источник',
        'key' => 'Параметр', 'value' => 'Значение',
    ];

    private const MONEY = ['amount', 'rate', 'contract_amount', 'day_rate'];

    private const LOOKUPS = [
        'project_id' => Project::class,
        'expense_category_id' => ExpenseCategory::class,
        'employee_id' => Employee::class,
        'user_id' => User::class,
    ];

    /** @var array<string, array<int, string>> */
    private static array $names = [];

    public static function modelLabel(?string $class): string
    {
        return self::MODELS[$class] ?? class_basename((string) $class);
    }

    public static function eventLabel(?string $event): string
    {
        return self::EVENTS[$event] ?? (string) $event;
    }

    /** Название записи: «Объект: Школа №5», «Расход №12 от 01.09.2026 на 5 000 ₽» */
    public static function subjectTitle(Activity $a): string
    {
        $s = $a->subject;
        $props = $a->properties;
        $attrs = $props['attributes'] ?? $props['old'] ?? [];
        $label = self::modelLabel($a->subject_type);

        $name = $s?->name ?? $attrs['name'] ?? null;
        if ($name) {
            return $label.': '.$name;
        }

        $parts = [$label.' №'.$a->subject_id];
        $date = $s?->date ?? (isset($attrs['date']) ? Carbon::parse($attrs['date']) : null);
        if ($date) {
            $parts[] = 'от '.Carbon::parse($date)->format('d.m.Y');
        }
        $amount = $s?->amount ?? $attrs['amount'] ?? null;
        if ($amount !== null) {
            $parts[] = 'на '.Money::fmt($amount);
        }
        if ($a->subject_type === ProjectBudget::class) {
            $cat = self::lookup('expense_category_id', $s?->expense_category_id ?? $attrs['expense_category_id'] ?? null);
            $parts = [$label.' «'.$cat.'»', '— '.self::lookup('project_id', $s?->project_id ?? $props['project_id'] ?? null)];
        }

        return implode(' ', $parts);
    }

    /** Ссылка на запись (если она ещё существует и у раздела есть страница) */
    public static function subjectUrl(Activity $a): ?string
    {
        $s = $a->subject;
        if (! $s) {
            return null;
        }

        return match ($a->subject_type) {
            Project::class => ProjectResource::getUrl('view', ['record' => $s]),
            Employee::class => EmployeeResource::getUrl('view', ['record' => $s]),
            ProjectBudget::class => ProjectResource::getUrl('view', ['record' => $s->project_id]),
            Expense::class => ExpenseResource::getUrl('edit', ['record' => $s]),
            WorkLog::class => WorkLogResource::getUrl('edit', ['record' => $s]),
            Income::class => IncomeResource::getUrl('edit', ['record' => $s]),
            Payout::class => PayoutResource::getUrl('edit', ['record' => $s]),
            Advance::class => AdvanceResource::getUrl('edit', ['record' => $s]),
            User::class => UserResource::getUrl('edit', ['record' => $s]),
            default => null,
        };
    }

    /**
     * Изменения строками «Поле: было → стало». Для создания — только «стало», для удаления — «было».
     *
     * @return list<string>
     */
    public static function changes(Activity $a): array
    {
        $new = (array) ($a->properties['attributes'] ?? []);
        $old = (array) ($a->properties['old'] ?? []);
        $model = $a->subject_type;
        $out = [];

        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $field) {
            $label = self::FIELDS[$field] ?? $field;
            $from = array_key_exists($field, $old) ? self::value($model, $field, $old[$field]) : null;
            $to = array_key_exists($field, $new) ? self::value($model, $field, $new[$field]) : null;

            if ($a->event === 'updated') {
                $out[] = $label.': '.($from ?? '—').' → '.($to ?? '—');
            } elseif (($to ?? $from) !== null) {
                $out[] = $label.': '.($to ?? $from);
            }
        }

        return $out;
    }

    public static function value(?string $model, string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (in_array($field, self::MONEY, true)) {
            return Money::fmt($value);
        }
        if (isset(self::LOOKUPS[$field])) {
            return self::lookup($field, $value);
        }
        if ($field === 'attachments') {
            return 'файлов: '.count((array) $value);
        }
        if (is_bool($value) || $field === 'is_active') {
            return $value ? 'да' : 'нет';
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(T|\s|$)/', $value)) {
            return Carbon::parse($value)->format('d.m.Y');
        }
        if ($enum = self::enumLabel($model, $field, $value)) {
            return $enum;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private static function enumLabel(?string $model, string $field, mixed $value): ?string
    {
        if (! $model || ! class_exists($model) || ! is_scalar($value)) {
            return null;
        }
        $cast = (new $model)->getCasts()[$field] ?? null;
        if ($cast && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
            $case = $cast::tryFrom($value);

            return $case instanceof HasLabel ? (string) $case->getLabel() : $case?->name;
        }

        return null;
    }

    private static function lookup(string $field, mixed $id): string
    {
        if ($id === null || $id === '') {
            return '—';
        }
        $class = self::LOOKUPS[$field];
        if (! isset(self::$names[$class][$id])) {
            $q = $class::query();
            if (method_exists($class, 'bootSoftDeletes')) {
                $q->withTrashed();
            }
            self::$names[$class][$id] = (string) ($q->find($id)?->name ?? '№'.$id);
        }

        return self::$names[$class][$id];
    }
}
