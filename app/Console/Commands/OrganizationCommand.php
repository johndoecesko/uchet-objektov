<?php

namespace App\Console\Commands;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** Служебная правка организации: список, переименование, адрес (slug), блокировка */
class OrganizationCommand extends Command
{
    protected $signature = 'app:organization {slug? : адрес организации; без него — список}
        {--name= : новое название}
        {--new-slug= : новый адрес /app/{slug}}
        {--block : заблокировать}
        {--unblock : разблокировать}';

    protected $description = 'Организации: список, переименование, смена адреса, блокировка';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        if (! $slug) {
            $this->table(['id', 'Адрес', 'Название', 'Статус', 'Участников', 'Создана'], Organization::withCount('memberships')->orderBy('id')->get()
                ->map(fn (Organization $o) => [$o->id, $o->slug, $o->name, $o->status->getLabel(), $o->memberships_count, $o->created_at?->format('d.m.Y')]));

            return self::SUCCESS;
        }

        $org = Organization::where('slug', $slug)->first();
        if (! $org) {
            $this->error("Организация «{$slug}» не найдена");

            return self::FAILURE;
        }

        if ($name = $this->option('name')) {
            if (! mb_check_encoding($name, 'UTF-8')) {
                $this->error('Название не в UTF-8 — проверьте кодировку консоли');

                return self::FAILURE;
            }
            $org->name = $name;
        }
        if ($newSlug = $this->option('new-slug')) {
            $v = Validator::make(['slug' => $newSlug], ['slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{1,58}$/', 'unique:organizations,slug,'.$org->id]]);
            if ($v->fails() || in_array($newSlug, Organization::RESERVED_SLUGS, true)) {
                $this->error('Адрес: латиница, цифры и дефис, 2–60 символов, не занят и не служебный');

                return self::FAILURE;
            }
            $org->slug = $newSlug;
        }
        if ($this->option('block')) {
            $org->status = OrganizationStatus::Blocked;
        }
        if ($this->option('unblock')) {
            $org->status = OrganizationStatus::Active;
        }

        $org->save();
        $this->info("Готово: «{$org->name}», адрес /app/{$org->slug}, статус: {$org->status->getLabel()}");

        return self::SUCCESS;
    }
}
