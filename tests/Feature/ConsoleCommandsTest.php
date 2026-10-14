<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Attention;
use App\Support\BackupOffsite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_command_rejects_cyrillic_email_before_insert(): void
    {
        $this->artisan('app:admin', ['email' => 'иван@bk.ru'])
            ->expectsOutputToContain('Некорректный email')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_admin_command_creates_admin(): void
    {
        $this->artisan('app:admin', ['email' => 'boss@example.ru'])
            ->expectsQuestion('Пароль (мин. 8 символов)', 'secret123')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'boss@example.ru')->first()->isAdmin());
    }

    public function test_backup_command_on_sqlite(): void
    {
        $dir = sys_get_temp_dir().'/uchet-backup-'.uniqid();
        $db = tempnam(sys_get_temp_dir(), 'db');
        file_put_contents($db, 'SQLite format 3');
        config(['backup.path' => $dir, 'database.connections.sqlite.database' => $db]);

        $this->artisan('app:backup', ['--no-files' => true])->assertSuccessful();

        $files = glob($dir.'/uchet-*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertSame('SQLite format 3', gzdecode(file_get_contents($files[0])));

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
        unlink($db);
    }

    private function backupToCloud(int $status): array
    {
        $dir = sys_get_temp_dir().'/uchet-backup-'.uniqid();
        $db = tempnam(sys_get_temp_dir(), 'db');
        file_put_contents($db, 'SQLite format 3');
        @unlink(BackupOffsite::marker());
        config(['backup.path' => $dir, 'database.connections.sqlite.database' => $db, 'backup.webdav' => [
            'url' => 'https://cloud.test/public.php/webdav/', 'user' => 'share-token', 'password' => 'share-pass',
        ]]);
        Http::fake(['cloud.test/*' => Http::response('', $status)]);

        $code = $this->artisan('app:backup', ['--no-files' => true])->run();
        $sent = Http::recorded();

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
        unlink($db);

        return [$code, $sent];
    }

    public function test_backup_is_uploaded_to_cloud(): void
    {
        [$code, $sent] = $this->backupToCloud(201);

        $this->assertSame(0, $code);
        $this->assertCount(1, $sent);
        [$request] = $sent[0];
        $this->assertSame('PUT', $request->method());
        $this->assertMatchesRegularExpression('#^https://cloud\.test/public\.php/webdav/uchet-\d{4}-\d{2}-\d{2}-\d{4}\.sql\.gz$#', $request->url());
        $this->assertTrue($request->hasHeader('Authorization', 'Basic '.base64_encode('share-token:share-pass')));
        $this->assertSame(0, BackupOffsite::daysSinceLast());
        $this->assertStringNotContainsString('Копия в облако', Attention::items()->pluck('text')->implode('|'));
    }

    public function test_cloud_failure_keeps_local_copy_and_shows_in_attention(): void
    {
        [$code] = $this->backupToCloud(401);

        $this->assertSame(1, $code);
        $this->assertNull(BackupOffsite::daysSinceLast());
        $this->assertStringContainsString('Копия в облако (Nextcloud) ещё ни разу не выгружалась', Attention::items()->pluck('text')->implode('|'));
    }
}
