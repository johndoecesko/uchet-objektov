<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\CurrentOrganization;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Запись истории изменений с организацией: раздел «История» и блоки на страницах показывают только своё.
 * Действия вне организации (администратор платформы, вход по ссылке) пишутся без организации и в организациях не видны.
 */
class Activity extends BaseActivity
{
    use BelongsToOrganization;

    /**
     * Организация — как у записи, которую изменили; иначе текущая.
     * Учётная запись (имя, почта) — личное и общее для всех организаций: в историю организаций не попадает.
     */
    protected function organizationIdForNewRecord(): ?int
    {
        $subject = $this->relationLoaded('subject') ? $this->getRelation('subject') : null;
        if ($subject instanceof User) {
            return null;
        }

        return $subject?->getAttribute('organization_id') ?? app(CurrentOrganization::class)->id();
    }

    protected function allowsNoOrganization(): bool
    {
        return true;
    }
}
