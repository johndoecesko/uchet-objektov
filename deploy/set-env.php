<?php
// Записывает значения в .env: php deploy/set-env.php KEY1 KEY2 ...
// Значения берутся из одноимённых переменных окружения, чтобы пароль не попадал в историю команд.
$file = dirname(__DIR__).'/.env';
$env = file_get_contents($file);

foreach (array_slice($argv, 1) as $key) {
    $value = trim((string) getenv($key));
    $quoted = strpos($value, "'") === false
        ? "'".$value."'"
        : '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    $line = $key.'='.$quoted;
    $k = preg_quote($key, '/');
    // сначала действующая строка, иначе закомментированный пример, иначе дописываем в конец
    $env = preg_replace_callback('/^'.$k.'=.*$/m', fn () => $line, $env, 1, $found);
    if (! $found) {
        $env = preg_replace_callback('/^#\s*'.$k.'=.*$/m', fn () => $line, $env, 1, $found);
    }
    if (! $found) {
        $env = rtrim($env)."\n".$line."\n";
    }
}

file_put_contents($file, $env);
