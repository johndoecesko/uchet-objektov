<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectResource;
use App\Filament\Widgets\ActiveProjects;
use App\Filament\Widgets\NeedsAttention;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Support\Attention;
use App\Support\Money;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_tells_a_story(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(4, Project::count());
        $this->assertSame(3, User::count());

        $texts = Attention::items()->pluck('text')->implode(' | ');
        $this->assertStringContainsString('Перерасход по статье «Материалы и оборудование»', $texts);
        $this->assertStringContainsString('Не отчитался за '.Money::fmt(12650), $texts);

        $engineer = Employee::where('name', 'Игорь Монтажов')->firstOrFail();
        $this->assertEqualsWithDelta(12650, $engineer->advanceBalance(), 0.01);

        $big = Project::where('name', 'like', 'ЖК%')->firstOrFail();
        $this->assertEqualsWithDelta(192000, $big->taxTotal(), 0.01);
        $this->assertGreaterThan(0, $big->forecastProfit());

        $this->assertEqualsWithDelta(200000, Project::where('name', 'like', 'Склад%')->firstOrFail()->customerDebt(), 0.01);
    }

    public function test_demo_admin_can_log_in_and_see_dashboard(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = User::where('email', 'admin@demo.local')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(DemoSeeder::PASSWORD, $admin->password));

        $this->actingAs($admin);
        $this->get('/')->assertOk();
        Livewire::test(ActiveProjects::class)->assertSee('Школа № 12');
        Livewire::test(NeedsAttention::class)->assertSee('Игорь Монтажов');
        $this->get(ProjectResource::getUrl('view', ['record' => Project::first()]))->assertOk();
    }

    public function test_demo_engineer_can_log_in_and_see_dashboard(): void
    {
        $this->seed(DemoSeeder::class);

        $engineer = User::where('email', 'engineer@demo.local')->firstOrFail();
        $this->actingAs($engineer);
        $this->get('/')->assertOk();
    }
}
