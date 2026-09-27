#!/usr/bin/env bash
# Первичная настройка на сервере (виртуальный хостинг или VPS).
# Запуск из папки проекта:  bash deploy/setup.sh
# Другая версия PHP:        PHP=/opt/php/8.3/bin/php bash deploy/setup.sh
set -euo pipefail
cd "$(dirname "$0")/.."
PHP=${PHP:-php}
COMPOSER=${COMPOSER:-composer}

"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2", ">=") ? 0 : 1);' \
  || { echo "Нужен PHP 8.2+. Укажите путь: PHP=/путь/к/php bash deploy/setup.sh"; exit 1; }

if [ ! -d vendor ]; then
  "$PHP" "$(command -v "$COMPOSER")" install --no-dev --optimize-autoloader --no-interaction
fi

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || "$PHP" artisan key:generate --force

read -r -p "Адрес сайта (https://uchet.example.ru): " APP_URL
read -r -p "Часовой пояс [Europe/Moscow]: " APP_TIMEZONE
read -r -p "MySQL: хост [127.0.0.1]: " DB_HOST
read -r -p "MySQL: имя базы: " DB_DATABASE
read -r -p "MySQL: пользователь [$DB_DATABASE]: " DB_USERNAME
APP_TIMEZONE=${APP_TIMEZONE:-Europe/Moscow}; DB_HOST=${DB_HOST:-127.0.0.1}; DB_USERNAME=${DB_USERNAME:-$DB_DATABASE}

while true; do
  read -rs -p "MySQL: пароль (ввод скрыт): " DB_PASSWORD; echo
  DB_PASSWORD="$(printf '%s' "$DB_PASSWORD" | tr -d '\r\n')"
  if ! command -v mysql >/dev/null || MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -u "$DB_USERNAME" "$DB_DATABASE" -e 'select 1' >/dev/null 2>&1; then
    break
  fi
  echo "База не приняла пароль, попробуйте ещё раз."
done

export APP_URL APP_TIMEZONE DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD
APP_ENV=production APP_DEBUG=false DB_CONNECTION=mysql DB_PORT=3306 SESSION_SECURE_COOKIE=true LOG_LEVEL=warning \
  "$PHP" deploy/set-env.php APP_ENV APP_DEBUG APP_URL APP_TIMEZONE LOG_LEVEL SESSION_SECURE_COOKIE \
  DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
unset DB_PASSWORD

"$PHP" artisan config:clear >/dev/null
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --force
"$PHP" artisan storage:link >/dev/null 2>&1 || true
"$PHP" artisan optimize

read -r -p "Email администратора: " EMAIL
read -r -p "Имя администратора [Администратор]: " NAME
"$PHP" artisan app:admin "$EMAIL" "${NAME:-Администратор}"

echo
echo "Готово: $APP_URL"
echo "Токен DaData (необязательно) — строка DADATA_TOKEN= в .env, затем: $PHP artisan optimize"
