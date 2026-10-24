<?php

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Filament\Pages\Tenancy\EditOrganizationProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

/** Фото профиля и логотип организации: закрытый диск, подписанные ссылки, старые файлы удаляются, без внешних сервисов */
class AvatarTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    public function test_user_uploads_replaces_and_removes_photo(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);

        Livewire::test(EditProfile::class)
            ->fillForm(['avatar_path' => UploadedFile::fake()->image('me.jpg', 300, 300)])
            ->call('save')->assertHasNoFormErrors();
        $first = $this->admin->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/', $first);
        Storage::disk('local')->assertExists($first);

        $url = $this->admin->fresh()->getFilamentAvatarUrl();
        $this->assertStringContainsString($first, $url);
        $this->assertSame($url, $this->admin->fresh()->getFilamentAvatarUrl(), 'в течение дня ссылка одна — картинка кэшируется');

        // замена: убрать старое и загрузить новое (тестовый помощник иначе добавляет файл к имеющемуся)
        Livewire::test(EditProfile::class)
            ->fillForm(['avatar_path' => []])
            ->fillForm(['avatar_path' => UploadedFile::fake()->image('new.png', 300, 300)])
            ->call('save')->assertHasNoFormErrors();
        $second = $this->admin->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);

        Livewire::test(EditProfile::class)->fillForm(['avatar_path' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull($this->admin->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_only_images_are_accepted(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);

        Livewire::test(EditProfile::class)
            ->fillForm(['avatar_path' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
            ->call('save')->assertHasFormErrors(['avatar_path']);
        $this->assertNull($this->admin->fresh()->avatar_path);
    }

    public function test_admin_sets_organization_logo(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);

        Livewire::test(EditOrganizationProfile::class)
            ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png', 400, 400)])
            ->call('save')->assertHasNoFormErrors();
        $logo = $this->org->fresh()->logo_path;
        $this->assertStringStartsWith('logos/', $logo);
        Storage::disk('local')->assertExists($logo);
        $this->assertStringContainsString($logo, $this->org->fresh()->getFilamentAvatarUrl());
    }

    public function test_photo_is_served_only_by_signed_link(): void
    {
        $path = 'avatars/test-'.uniqid().'.png';
        Storage::disk('local')->put($path, UploadedFile::fake()->image('a.png', 10, 10)->getContent());
        try {
            $this->admin->forceFill(['avatar_path' => $path])->saveQuietly();
            $url = $this->admin->getFilamentAvatarUrl();
            $this->assertStringContainsString('signature=', $url);
            $this->get($url)->assertOk();
            $this->get(strtok($url, '?'))->assertForbidden();
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function test_without_photo_initials_are_drawn_locally(): void
    {
        $this->actingAs($this->admin);
        $html = $this->get('/app/montazh/members')->assertOk()->getContent();

        $this->assertStringNotContainsString('ui-avatars.com', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('>А</text>', base64_decode(explode(',', filament()->getUserAvatarUrl($this->admin))[1]));
    }
}
