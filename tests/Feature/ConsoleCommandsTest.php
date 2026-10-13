<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
