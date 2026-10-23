<?php

namespace App\Support;

use App\Models\Organization;
use Closure;
use Filament\Facades\Filament;

/**
 * Текущая организация (тенант) для запросов к данным.
 * В панели — организация из адреса /app/{slug} (её выставляет Filament).
 * Вне панели (бот, консоль, очередь, отчёты) — задаётся явно через set() или run().
 */
class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        if ($this->organization) {
            return $this->organization;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Organization ? $tenant : null;
    }

    public function id(): ?int
    {
        return $this->get()?->getKey();
    }

    /** Выполнить код от имени организации и вернуть прежнее состояние */
    public function run(Organization $organization, Closure $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback();
        } finally {
            $this->organization = $previous;
        }
    }
}
