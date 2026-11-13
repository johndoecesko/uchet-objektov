<?php

namespace Tests\Feature\Concerns;

use App\Models\Organization;
use App\Models\User;
use App\Support\Demo\DemoReset;

/** Публичное демо в тестах: включить, создать и заполнить; войти кнопкой «Посмотреть демо» */
trait UsesDemo
{
    protected Organization $demo;

    protected User $demoAdmin;

    protected function makeDemo(): void
    {
        config(['demo.enabled' => true]);
        $reset = app(DemoReset::class);
        $this->demo = $reset->ensureOrganization(create: true);
        $reset->run();
        $this->demo->refresh();
        $this->demoAdmin = User::where('email', DemoReset::PERSONAS['admin'][0])->firstOrFail();
    }

    /** Войти в демо как гость с главной; дальше запросы и Livewire — от демо-пользователя в демо-организации */
    protected function enterDemo(): void
    {
        auth()->forgetUser();
        $this->post('/demo')->assertRedirect('/app/demo');
        $this->assertAuthenticatedAs($this->demoAdmin);
        $this->useOrganization($this->demo);
    }
}
