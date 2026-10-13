<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\Process\Process;

class Backup extends Command
{
    protected $signature = 'app:backup {--no-files : Только база, без папки с чеками}';

    protected $description = 'Резервная копия базы (sql.gz) и чеков (tar.gz) в config(backup.path) с ротацией';

    public function handle(): int
    {
        $dir = rtrim((string) config('backup.path'), '/\\');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error("Не удалось создать папку {$dir}");

            return self::FAILURE;
        }

        $now = now();

        try {
            $sql = $this->dumpDatabase($dir, $now);
            $this->info('База: '.$sql.' ('.$this->size($sql).')');

            if (! $this->option('no-files')) {
                $files = $this->archiveFiles($dir, $now);
                $this->info('Чеки: '.$files.' ('.$this->size($files).')');
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (self::rotate($dir, $now, (int) config('backup.keep_days'), (int) config('backup.keep_weeks')) as $old) {
            $this->line('Удалена старая копия: '.basename($old));
        }

        return self::SUCCESS;
    }

    private function dumpDatabase(string $dir, Carbon $now): string
    {
        $name = config('database.default');
        $db = config("database.connections.{$name}");
        $target = $dir.'/uchet-'.$now->format('Y-m-d-Hi').'.sql.gz';

        if ($db['driver'] === 'sqlite') {
            // локальная демо-версия: просто сжатая копия файла базы
            $this->gzipFile($db['database'], $target);

            return $target;
        }

        if (! in_array($db['driver'], ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Драйвер {$db['driver']} не поддерживается");
        }

        // Пароль — только во временном файле с правами 600: не в аргументах процесса и не в выводе
        $cnf = tempnam(sys_get_temp_dir(), 'uchet-my');
        $raw = $dir.'/.dump-'.$now->format('YmdHis').'.sql';
        chmod($cnf, 0600);

        try {
            file_put_contents($cnf, implode("\n", [
                '[client]',
                'user='.$this->cnfValue($db['username'] ?? ''),
                'password='.$this->cnfValue($db['password'] ?? ''),
                'host='.$this->cnfValue($db['host'] ?? '127.0.0.1'),
                'port='.(int) ($db['port'] ?? 3306),
                ...(! empty($db['unix_socket']) ? ['socket='.$this->cnfValue($db['unix_socket'])] : []),
                '',
            ]));

            $process = new Process([
                config('backup.mysqldump'),
                '--defaults-extra-file='.$cnf,
                '--single-transaction',
                '--quick',
                '--no-tablespaces',
                '--routines',
                '--default-character-set='.($db['charset'] ?? 'utf8mb4'),
                '--result-file='.$raw,
                $db['database'],
            ]);
            $process->setTimeout(600)->run();

            if (! $process->isSuccessful()) {
                // stderr mysqldump не содержит пароля; обрезаем, чтобы не тащить в лог лишнего
                throw new RuntimeException('mysqldump: '.mb_substr(trim($process->getErrorOutput()), 0, 300));
            }

            $this->gzipFile($raw, $target);
        } finally {
            @unlink($cnf);
            @unlink($raw);
        }

        return $target;
    }

    private function archiveFiles(string $dir, Carbon $now): string
    {
        $target = $dir.'/uchet-files-'.$now->format('Y-m-d').'.tar.gz';
        $process = new Process([config('backup.tar'), '-czf', $target, '-C', storage_path('app'), 'private']);
        $process->setTimeout(600)->run();

        if (! $process->isSuccessful()) {
            @unlink($target);
            throw new RuntimeException('tar: '.mb_substr(trim($process->getErrorOutput()), 0, 300));
        }
        @chmod($target, 0600);

        return $target;
    }

    private function gzipFile(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');
        if (! $in || ! $out) {
            throw new RuntimeException("Не удалось записать {$target}");
        }
        while (! feof($in)) {
            gzwrite($out, fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        @chmod($target, 0600);
    }

    private function cnfValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function size(string $file): string
    {
        return number_format(filesize($file) / 1024, 0, ',', ' ').' КБ';
    }

    /**
     * Ротация: всё моложе $keepDays дней хранится, дальше — самая свежая копия
     * каждой недели до $keepWeeks недель, остальное удаляется. База и чеки — раздельно.
     *
     * @return list<string> удалённые файлы
     */
    public static function rotate(string $dir, Carbon $now, int $keepDays, int $keepWeeks): array
    {
        $deleted = [];

        foreach (['/^uchet-(\d{4}-\d{2}-\d{2})-\d{4}\.sql\.gz$/', '/^uchet-files-(\d{4}-\d{2}-\d{2})\.tar\.gz$/'] as $pattern) {
            $byDate = [];
            foreach (scandir($dir) ?: [] as $file) {
                if (preg_match($pattern, $file, $m)) {
                    $byDate[$file] = Carbon::parse($m[1]);
                }
            }
            krsort($byDate); // имена сортируются по времени: свежие первыми

            $weeks = [];
            foreach ($byDate as $file => $date) {
                $age = (int) $date->diffInDays($now->copy()->startOfDay());
                if ($age < $keepDays) {
                    continue;
                }
                $week = $date->format('o-W');
                if ($age < $keepWeeks * 7 && ! isset($weeks[$week])) {
                    $weeks[$week] = true;

                    continue;
                }
                unlink($dir.'/'.$file);
                $deleted[] = $dir.'/'.$file;
            }
        }

        return $deleted;
    }
}
