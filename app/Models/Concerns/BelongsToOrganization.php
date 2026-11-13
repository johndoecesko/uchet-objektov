<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\Demo\Demo;
use App\Support\OrganizationMismatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись принадлежит организации.
 * - Глобальный scope: когда текущая организация известна, видны только её записи.
 * - При создании organization_id берётся из текущей организации; если её нет — исключение (не NULL).
 * - Ссылки на другие записи (объект, статья, сотрудник…) должны вести в ту же организацию.
 */
trait BelongsToOrganization
{
    /** Имя scope, который Filament вешает на модели ресурсов панели admin ("{id панели}_tenancy") */
    public const FILAMENT_TENANCY_SCOPE = 'admin_tenancy';

    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            if ($id = app(CurrentOrganization::class)->id()) {
                $query->where($query->qualifyColumn('organization_id'), $id);
            }
        });

        // saving срабатывает раньше creating — организацию проставляем здесь, до проверки ссылок
        static::saving(function (Model $model) {
            if (! $model->exists && empty($model->organization_id)) {
                $model->organization_id = $model->organizationIdForNewRecord();
                if (empty($model->organization_id) && ! $model->allowsNoOrganization()) {
                    throw new OrganizationMismatch('Запись '.class_basename($model).' создаётся без организации');
                }
            }
            // Демо только для чтения: после того как организация записи известна
            Demo::assertWritable($model);
            $model->assertReferencesInOrganization();
        });

        // Удаление (в корзину и навсегда), восстановление из корзины — тоже запись
        static::deleting(fn (Model $model) => Demo::assertWritable($model));
        if (method_exists(static::class, 'restoring')) {
            static::restoring(fn (Model $model) => Demo::assertWritable($model));
        }
        if (method_exists(static::class, 'forceDeleting')) {
            static::forceDeleting(fn (Model $model) => Demo::assertWritable($model));
        }
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Запрос без ограничения текущей организацией — только для служебных мест (права, бот, платформа).
     * Снимает и наш scope, и такой же scope Filament для ресурсов панели (admin_tenancy).
     */
    public static function acrossOrganizations(): Builder
    {
        return static::withoutGlobalScopes(['organization', self::FILAMENT_TENANCY_SCOPE]);
    }

    /** Организация новой записи — текущая */
    protected function organizationIdForNewRecord(): ?int
    {
        return app(CurrentOrganization::class)->id();
    }

    /** Можно ли создать запись вне организации (история изменений платформенных действий) */
    protected function allowsNoOrganization(): bool
    {
        return false;
    }

    /**
     * Внешние ключи на записи организации: колонка → модель.
     *
     * @return array<string, class-string<Model>>
     */
    protected function organizationReferences(): array
    {
        return [];
    }

    protected function assertReferencesInOrganization(): void
    {
        foreach ($this->organizationReferences() as $column => $class) {
            $id = $this->getAttribute($column);
            if ($id === null || ! ($this->isDirty($column) || $this->isDirty('organization_id'))) {
                continue;
            }
            $org = $class::withoutGlobalScopes()->whereKey($id)->value('organization_id');
            if ((int) $org !== (int) $this->organization_id) {
                throw new OrganizationMismatch(class_basename($this)."::{$column} = {$id} ведёт в другую организацию");
            }
        }
    }
}
