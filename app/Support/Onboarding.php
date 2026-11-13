<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\IncomeResource;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\ProjectResource;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Support\Max\MaxApi;

/**
 * Чек-лист «Первые шаги» на главной: что уже сделано в организации по-настоящему (пример не в счёт).
 * Офис (админ, менеджер) — объект, бюджет, сотрудник, расход, поступление, приглашение (только админ), бот MAX.
 * Прораб — свой расход и бот MAX. Скрыть можно у себя (organization_user.onboarding_hidden_at); всё сделано — не виден.
 */
class Onboarding
{
    /**
     * @return list<array{key: string, label: string, hint: string, done: bool, url: ?string, action: ?string}>
     */
    public static function steps(User $user, Organization $org): array
    {
        return app(CurrentOrganization::class)->run($org, function () use ($user, $org) {
            $role = $user->roleIn($org);
            $realProjects = Project::query()->real()->select('id');
            $steps = [];

            if (in_array($role, [UserRole::Admin, UserRole::Manager], true)) {
                $steps[] = self::step('project', 'Добавьте первый объект', 'Название, заказчик, сумма договора и процент налогов',
                    Project::query()->real()->exists(), ProjectResource::getUrl('create'));
                $steps[] = self::step('budget', 'Заполните бюджет объекта по статьям', 'Из сметы или КП — тогда видно освоение и перерасход',
                    ProjectBudget::query()->whereIn('project_id', $realProjects)->exists(), ProjectResource::getUrl('index'));
                $steps[] = self::step('employee', 'Добавьте сотрудников', 'Ставка за день — зарплата из журнала работ посчитается сама',
                    Employee::query()->real()->exists(), EmployeeResource::getUrl('create'));
                $steps[] = self::step('expense', 'Внесите первый расход', 'С фото чека; прораб может вносить свои расходы с телефона',
                    Expense::query()->whereIn('project_id', $realProjects)->exists(), ExpenseResource::getUrl('create'));
                $steps[] = self::step('income', 'Отметьте оплату от заказчика', 'Аванс или этап — видно, сколько заказчик ещё должен',
                    Income::query()->whereIn('project_id', $realProjects)->exists(), IncomeResource::getUrl('create'));
                if ($role === UserRole::Admin) {
                    $steps[] = self::step('invite', 'Пригласите сотрудников в учёт', 'Ссылкой в мессенджер или по почте; роль — у каждого своя',
                        Membership::query()->count() > 1 || Invitation::query()->exists(), MemberResource::getUrl('index'));
                }
            } elseif ($role === UserRole::Foreman) {
                $steps[] = self::step('expense', 'Внесите первый расход', 'Фото чека, сумма, объект — с телефона это минута',
                    Expense::query()->where('created_by', $user->id)->exists(), ExpenseResource::getUrl('create'));
            }

            if ($role !== null && MaxApi::enabled()) {
                $steps[] = self::step('max', 'Подключите бота MAX', 'Присылайте боту фото чеков — расход внесётся сам',
                    $user->max_user_id !== null, null, 'connectMax');
            }

            return $steps;
        });
    }

    /** Чек-лист не показывать: скрыт участником или всё сделано */
    public static function hidden(User $user, Organization $org): bool
    {
        if ($user->membershipIn($org)?->onboarding_hidden_at !== null) {
            return true;
        }

        $steps = self::steps($user, $org);

        return $steps === [] || collect($steps)->every(fn (array $s) => $s['done']);
    }

    /** «Скрыть» — запросом, без истории изменений: это настройка экрана, а не данные организации */
    public static function hide(User $user, Organization $org, bool $hidden = true): void
    {
        abort_if($user->is_demo || $org->is_demo, 403, 'В демо можно только смотреть');
        Membership::acrossOrganizations()
            ->where('organization_id', $org->id)->where('user_id', $user->id)
            ->update(['onboarding_hidden_at' => $hidden ? now() : null]);
        $user->forgetMemberships();
    }

    private static function step(string $key, string $label, string $hint, bool $done, ?string $url, ?string $action = null): array
    {
        return ['key' => $key, 'label' => $label, 'hint' => $hint, 'done' => $done, 'url' => $url, 'action' => $action];
    }
}
