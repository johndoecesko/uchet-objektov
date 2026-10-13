<?php

namespace Tests\Unit;

use App\Console\Commands\Backup;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class BackupRotationTest extends TestCase
{
    public function test_keeps_daily_for_two_weeks_then_weekly_up_to_eight_weeks(): void
    {
        $dir = sys_get_temp_dir().'/uchet-rotate-'.uniqid();
        mkdir($dir);
        $now = Carbon::parse('2026-10-14 03:10');

        // по копии на каждый день за 70 дней + ручная копия сегодня + посторонний файл
        for ($i = 0; $i < 70; $i++) {
            $d = $now->copy()->subDays($i);
            touch($dir.'/uchet-'.$d->format('Y-m-d').'-0310.sql.gz');
            touch($dir.'/uchet-files-'.$d->format('Y-m-d').'.tar.gz');
        }
        touch($dir.'/uchet-2026-10-14-1530.sql.gz');
        touch($dir.'/other-site.zip');

        Backup::rotate($dir, $now, 14, 8);

        $left = array_values(array_diff(scandir($dir), ['.', '..']));
        $sql = preg_grep('/^uchet-\d{4}-.*\.sql\.gz$/', $left);
        $files = preg_grep('/^uchet-files-/', $left);

        $this->assertContains('other-site.zip', $left);
        $this->assertContains('uchet-2026-10-14-1530.sql.gz', $left);
        $this->assertContains('uchet-2026-10-01-0310.sql.gz', $left);      // 13 дней — суточная
        $this->assertNotContains('uchet-2026-08-01-0310.sql.gz', $left);   // старше 8 недель
        // 15 суточных (14 дней + ручная сегодня) и не больше 7 недельных (недели 3–8)
        $this->assertLessThanOrEqual(15 + 7, count($sql));
        $this->assertGreaterThanOrEqual(15 + 5, count($sql));
        $this->assertCount(count($sql) - 1, $files);

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }
}
