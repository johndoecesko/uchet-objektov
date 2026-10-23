<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\UserRole;
use App\Models\Organization;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** «Создайте организацию»: после первого входа без организации и из меню организаций. Создатель — администратор */
class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Создать организацию';
    }

    public function getHeading(): string
    {
        return auth()->user()?->organizations()->exists() ? 'Новая организация' : 'Создайте организацию';
    }

    public function getSubheading(): ?string
    {
        return 'Организация — это ваша компания: объекты, сотрудники, расходы и участники. Позже можно пригласить коллег.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(EditOrganizationProfile::organizationFields());
    }

    protected function handleRegistration(array $data): Model
    {
        $org = Organization::create(['name' => $data['name'], 'inn' => $data['inn'] ?? null]);
        $org->addMember(auth()->user(), UserRole::Admin);

        return $org;
    }
}
