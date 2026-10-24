<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\Register;
use App\Filament\Pages\Tenancy\EditOrganizationProfile;
use App\Filament\Pages\Tenancy\RegisterOrganization;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\MemberResource\Pages\EditMember;
use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

/** Организации: создание, участники, приглашения, регистрация по приглашению, адреса, панель платформы */
class OrganizationFlowTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    public function test_new_organization_gets_default_categories_and_unique_slug(): void
    {
        $o = Organization::create(['name' => 'Моя организация']);
        $this->assertSame('moya-organizatsiya', $o->slug);
        $this->assertSame('moya-organizatsiya-2', Organization::create(['name' => 'Моя организация'])->slug);
        $this->assertSame('login-2', Organization::create(['name' => 'Login'])->slug, 'служебные адреса заняты');

        $cats = ExpenseCategory::acrossOrganizations()->where('organization_id', $o->id)->get();
        $this->assertCount(9, $cats);
        $this->assertSame(1, $cats->where('is_labor', true)->count());
        $this->assertSame(1, $cats->where('is_tax', true)->count());
    }

    public function test_user_without_organization_creates_one_and_becomes_admin(): void
    {
        $u = User::create(['name' => 'Новичок', 'email' => 'new@test.ru', 'password' => 'secret123']);
        $u->forceFill(['email_verified_at' => now()])->save();
        $this->useOrganization(null);
        $this->actingAs($u);

        $this->get('/app')->assertRedirect('/app/new');
        $this->get('/app/new')->assertOk()->assertSee('Создайте организацию');

        Livewire::test(RegisterOrganization::class)
            ->fillForm(['name' => 'ООО «Новый монтаж»', 'inn' => '6450000001'])
            ->call('register')
            ->assertHasNoFormErrors()
            ->assertRedirect('/app/ooo-novyy-montazh');

        $org = Organization::where('inn', '6450000001')->firstOrFail();
        $this->assertSame(UserRole::Admin, $u->forgetMemberships()->roleIn($org));
        $this->assertSame($u->id, $org->created_by);
        $this->assertSame(9, ExpenseCategory::acrossOrganizations()->where('organization_id', $org->id)->count());

        Livewire::test(RegisterOrganization::class)->fillForm(['name' => 'Х', 'inn' => '123'])->call('register')->assertHasFormErrors(['inn']);
    }

    public function test_organization_profile_only_for_admin(): void
    {
        $this->actingAs($this->admin);
        $this->get('/app/montazh/profile')->assertOk()->assertSee('/app/montazh');
        Livewire::test(EditOrganizationProfile::class)->fillForm(['name' => 'ООО «Монтаж плюс»'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('ООО «Монтаж плюс»', $this->org->fresh()->name);
        $this->assertSame('montazh', $this->org->fresh()->slug, 'адрес при переименовании не меняется');
    }

    public function test_organization_profile_forbidden_for_foreman(): void
    {
        $this->actingAs($this->foreman)->get('/app/montazh/profile')->assertNotFound();
    }

    public function test_link_invitation_lets_new_person_register_and_join(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ListMembers::class)->callAction('inviteLink', data: ['role' => UserRole::Foreman->value])->assertHasNoActionErrors();
        $invite = Invitation::firstOrFail();
        $this->assertNull($invite->email);
        $this->assertSame($this->admin->id, $invite->created_by);
        auth()->forgetUser();
        $this->useOrganization(null);

        // регистрация закрыта для всех, кроме пришедших по приглашению
        $this->assertFalse(config('auth.registration_enabled'));
        $this->get('/app/register')->assertNotFound();

        $this->get($invite->url())->assertOk()->assertSee('Приглашение в «ООО «Монтаж»»', false)->assertSee('Зарегистрироваться');
        $this->get('/app/register')->assertOk();

        Livewire::test(Register::class)
            ->fillForm(['name' => 'Монтажник Петя', 'email' => 'petya@test.ru', 'password' => 'Str0ng-pass!', 'passwordConfirmation' => 'Str0ng-pass!'])
            ->call('register')->assertHasNoFormErrors();
        $petya = User::where('email', 'petya@test.ru')->firstOrFail();
        $this->assertNull($petya->email_verified_at, 'ссылка не подтверждает почту');

        $this->post(route('invite.accept', $invite->id))->assertRedirect('/app/montazh');
        $this->assertSame(UserRole::Foreman, $petya->forgetMemberships()->roleIn($this->org));
        $this->assertNotNull($invite->fresh()->accepted_at);
        $this->assertSame($petya->id, $invite->fresh()->accepted_by);

        // ссылка одноразовая
        $this->get($invite->url())->assertOk()->assertSee('Приглашение недействительно');
    }

    public function test_email_invitation_is_sent_and_bound_to_email(): void
    {
        Notification::fake();
        $this->actingAs($this->admin);
        Livewire::test(ListMembers::class)
            ->callAction('inviteEmail', data: ['email' => 'Manager@Test.ru', 'role' => UserRole::Manager->value])
            ->assertHasNoActionErrors();

        $invite = Invitation::firstOrFail();
        $this->assertSame('manager@test.ru', $invite->email);
        Notification::assertSentOnDemand(OrganizationInvitation::class, function (OrganizationInvitation $n, array $channels, object $notifiable) use ($invite) {
            $mail = $n->toMail($notifiable);

            return $notifiable->routes['mail'] === 'manager@test.ru'
                && str_contains($mail->subject, 'ООО «Монтаж»')
                && $mail->actionUrl === $invite->url();
        });
        auth()->forgetUser();
        $this->useOrganization(null);

        // по почтовому приглашению почта подставлена и считается подтверждённой
        $this->get($invite->url())->assertOk();
        Livewire::test(Register::class)
            ->assertFormSet(['email' => 'manager@test.ru'])
            ->fillForm(['name' => 'Марина', 'email' => 'other@test.ru', 'password' => 'Str0ng-pass!', 'passwordConfirmation' => 'Str0ng-pass!'])
            ->call('register')->assertHasNoFormErrors();
        $this->assertNull(User::where('email', 'other@test.ru')->first(), 'почту из приглашения не подменить');
        $m = User::where('email', 'manager@test.ru')->firstOrFail();
        $this->assertNotNull($m->email_verified_at);

        $this->post(route('invite.accept', $invite->id))->assertRedirect('/app/montazh');
        $this->assertSame(UserRole::Manager, $m->forgetMemberships()->roleIn($this->org));
    }

    public function test_email_invitation_cannot_be_accepted_by_other_account(): void
    {
        $invite = Invitation::create(['email' => 'someone@test.ru', 'role' => UserRole::Admin]);
        $stranger = User::create(['name' => 'Чужой', 'email' => 'stranger@test.ru', 'password' => 'secret123']);
        $this->useOrganization(null);
        $this->actingAs($stranger);

        $this->get($invite->url())->assertOk()->assertSee('отправлено на someone@test.ru');
        $this->post(route('invite.accept', $invite->id))->assertForbidden();
        $this->assertNull($stranger->roleIn($this->org));
    }

    public function test_invitation_link_with_bad_signature_or_expired_is_rejected(): void
    {
        $invite = Invitation::create(['role' => UserRole::Admin]);
        $this->useOrganization(null);

        $this->get(route('invite.show', $invite->id))->assertOk()->assertSee('Приглашение недействительно');
        $this->get($invite->url().'x')->assertOk()->assertSee('Приглашение недействительно');
        $this->get('/app/register')->assertNotFound();

        $url = $invite->url();
        $this->travel(8)->days();
        $this->get($url)->assertOk()->assertSee('Приглашение недействительно');

        $stranger = User::create(['name' => 'Чужой', 'email' => 'stranger@test.ru', 'password' => 'secret123']);
        $this->actingAs($stranger)->post(route('invite.accept', $invite->id))->assertForbidden();
    }

    public function test_registration_open_when_enabled(): void
    {
        config(['auth.registration_enabled' => true]);
        $this->useOrganization(null);
        $this->get('/app/login')->assertOk()->assertSee('Нет учётной записи?');
        $this->get('/app/register')->assertOk();
    }

    public function test_login_page_hides_registration_when_closed(): void
    {
        $this->useOrganization(null);
        $this->get('/app/login')->assertOk()->assertDontSee('Нет учётной записи?');
    }

    public function test_members_management(): void
    {
        $manager = $this->member('Менеджер', 'm@test.ru', UserRole::Manager);
        $this->emp->update(['user_id' => $manager->id]);
        $own = Membership::where('user_id', $this->admin->id)->first();
        $mm = Membership::where('user_id', $manager->id)->first();

        $this->actingAs($this->admin);
        $this->get(MemberResource::getUrl('index'))->assertOk()->assertSee('m@test.ru')->assertSee('Пригласить по почте');

        // себя убрать нельзя, последнего администратора — понизить нельзя
        Livewire::test(ListMembers::class)->assertActionHidden(TestAction::make(DeleteAction::class)->table($own));
        Livewire::test(EditMember::class, ['record' => $own->getKey()])->fillForm(['role' => UserRole::Manager->value])->call('save');
        $this->assertSame(UserRole::Admin, $own->fresh()->role);

        Livewire::test(EditMember::class, ['record' => $mm->getKey()])->fillForm(['role' => UserRole::Foreman->value])->call('save')->assertHasNoFormErrors();
        $this->assertSame(UserRole::Foreman, $mm->fresh()->role);

        Livewire::test(ListMembers::class)->callAction(TestAction::make(DeleteAction::class)->table($mm));
        $this->assertNull(Membership::find($mm->id));
        $this->assertNotNull(User::find($manager->id), 'аккаунт остаётся');
        $this->assertNull($this->emp->fresh()->user_id, 'сотрудник отвязан');
    }

    public function test_members_page_forbidden_for_manager_and_foreman(): void
    {
        $this->actingAs($this->foreman)->get(MemberResource::getUrl('index'))->assertForbidden();
    }

    public function test_go_redirects_to_current_organization_and_old_paths_are_moved(): void
    {
        $this->useOrganization(null);
        $this->get('/login')->assertStatus(301)->assertRedirect('/app/login');
        $this->get('/projects/5')->assertStatus(301)->assertRedirect('/go/projects/5');
        $this->get('/users')->assertStatus(301)->assertRedirect('/go/members');
        $this->get('/go/expenses/create')->assertRedirect('/app/login');

        $other = Organization::create(['name' => 'Вторая']);
        $other->addMember($this->admin, UserRole::Admin);
        $this->admin->forceFill(['current_organization_id' => $other->id])->save();

        $this->actingAs($this->admin);
        $this->get('/go/expenses/create')->assertRedirect('/app/vtoraya/expenses/create');
        $this->get('/')->assertRedirect('/app');
        $this->get('/app')->assertRedirect('/app/vtoraya');

        // открыл организацию — она становится организацией по умолчанию
        $this->get('/app/montazh')->assertOk();
        $this->assertSame($this->org->id, $this->admin->fresh()->current_organization_id);
    }

    public function test_platform_panel_only_for_platform_admin(): void
    {
        $this->actingAs($this->admin)->get('/platform')->assertForbidden();
    }

    public function test_platform_admin_sees_service_summary_without_money(): void
    {
        $this->fillData();
        $boss = User::forceCreate(['name' => 'Платформа', 'email' => 'p@test.ru', 'password' => 'secret123', 'is_platform_admin' => true]);
        $this->useOrganization(null);
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($boss);

        $this->get('/platform')->assertOk()->assertSee('Организаций');
        $this->get('/platform/organizations')->assertOk()->assertSee('ООО «Монтаж»')
            ->assertDontSee('Школа №5')->assertDontSee('1 000 000');
        $this->get('/platform/users')->assertOk()->assertSee('a@test.ru');
        $this->get('/app')->assertRedirect('/app/new'); // в организации без приглашения не попасть
    }

    public function test_platform_admin_blocks_organization(): void
    {
        $boss = User::forceCreate(['name' => 'Платформа', 'email' => 'p@test.ru', 'password' => 'secret123', 'is_platform_admin' => true]);
        $this->useOrganization(null);
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($boss);

        Livewire::test(\App\Filament\Platform\Resources\OrganizationResource\Pages\ListOrganizations::class)
            ->callAction(TestAction::make('block')->table($this->org));
        $this->assertTrue($this->org->fresh()->isBlocked());

        Livewire::test(\App\Filament\Platform\Resources\UserResource\Pages\ListUsers::class)
            ->callAction(TestAction::make('block')->table($this->foreman));
        $this->assertFalse($this->foreman->fresh()->is_active);
    }

    public function test_admin_command_can_add_organization_admin(): void
    {
        $this->artisan('app:admin', ['email' => 'Boss@Example.ru', '--org' => 'montazh'])
            ->expectsQuestion('Пароль (мин. 8 символов)', 'secret123')
            ->assertSuccessful();
        $boss = User::where('email', 'boss@example.ru')->firstOrFail();
        $this->assertTrue($boss->is_platform_admin);
        $this->assertSame(UserRole::Admin, $boss->roleIn($this->org));
    }

    public function test_organization_command_renames_and_changes_slug(): void
    {
        $this->artisan('app:organization', ['slug' => 'montazh', '--name' => 'ООО «Системы»', '--new-slug' => 'sistemy'])->assertSuccessful();
        $this->assertSame(['ООО «Системы»', 'sistemy'], [$this->org->fresh()->name, $this->org->fresh()->slug]);
        $this->artisan('app:organization', ['slug' => 'sistemy', '--new-slug' => 'login'])->assertFailed();
        $this->artisan('app:organization')->assertSuccessful()->expectsOutputToContain('sistemy');
    }
}
