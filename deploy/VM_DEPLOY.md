# Запуск на чистой виртуальной машине

Инструкция рассчитана на Ubuntu 22.04/24.04 LTS. Production-контур состоит из Caddy, WordPress/PHP и MariaDB. Caddy автоматически получает и обновляет TLS-сертификат.

## 1. Подготовить сервер и DNS

Минимально рекомендуются 2 vCPU, 4 ГБ RAM и 30 ГБ SSD. В firewall/security group откройте:

- `22/tcp` для SSH, желательно только с административных адресов;
- `80/tcp` для проверки домена и перенаправления на HTTPS;
- `443/tcp` и `443/udp` для HTTPS/HTTP3.

Создайте DNS `A`/`AAAA` для домена конференций на публичный IP VM и дождитесь обновления DNS до запуска Caddy.

## 2. Установить Docker

```bash
ssh user@SERVER_IP
git clone <URL_РЕПОЗИТОРИЯ> wordpress-conference-service
cd wordpress-conference-service
sudo bash deploy/install-docker-ubuntu.sh
sudo docker run --rm hello-world
docker compose version
```

Если репозиторий недоступен, загрузите архив проекта и распакуйте его в отдельный каталог.

## 3. Создать production env

```bash
cp deploy/.env.production.example deploy/.env.production
nano deploy/.env.production
chmod 600 deploy/.env.production
```

Обязательно замените:

- `SITE_DOMAIN` и `LETSENCRYPT_EMAIL`;
- `WORDPRESS_DB_PASSWORD` и `MARIADB_ROOT_PASSWORD` на разные случайные пароли;
- SMTP host/port/login/from и `FYREMEZZONINE_SMTP_PASSWORD` на рабочие учетные данные.

Файл `.env.production` исключен из Git. Не пересылайте его вместе с исходниками и резервными копиями.

## 4. Проверить и запустить стек

```bash
docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml config >/dev/null

docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml up -d

docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml ps
```

Откройте `https://ВАШ_ДОМЕН/`. Прямой порт WordPress наружу не публикуется: весь трафик проходит через Caddy.

## 5. Настроить WordPress

1. Пройти штатный мастер WordPress и создать уникального администратора.
2. Активировать `Fyremezzonine Conference Manager` и `Fyremezzonine WP Theme`.
3. Открыть `Настройки -> Постоянные ссылки` и сохранить структуру.
4. Открыть `Инструменты -> Здоровье сайта` и устранить критические проверки.
5. Создать редакторов с ролью `Редактор` или `Автор`; системное администрирование оставить только роли `Администратор`.
6. Настроить SMTP в `Конференции -> Почта`, отправить тест и пройти реальную регистрацию с кодом.

Плагин сам применит версионируемые миграции таблиц заявок, партнеров и журнала действий через WordPress `dbDelta`.

## 6. Проверить сервис

```bash
curl -I https://ВАШ_ДОМЕН/
docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml logs --tail=100 caddy wordpress db
```

Пройдите приемочный список из `PRODUCTION_READINESS.md`. Особое внимание: роли, публикация конференции, SMTP-код, дедлайн, заявки, партнеры, персональные данные и мобильная версия.

## 7. Резервное копирование

Разовый backup БД и uploads:

```bash
bash deploy/backup.sh deploy/.env.production
```

Архивы появятся в `deploy/backups/`, права каталога ограничиваются, локальное хранение по умолчанию 14 дней. Для production настройте ежедневный cron и перенос копий в отдельное зашифрованное хранилище:

```cron
15 2 * * * cd /opt/wordpress-conference-service && /usr/bin/bash deploy/backup.sh deploy/.env.production >> /var/log/conference-backup.log 2>&1
```

До запуска обязательно восстановите свежую копию на отдельном staging-стенде. Непроверенный backup не считается резервной копией.

## 8. Обновление

1. Создать backup.
2. Развернуть обновление сначала на staging и выполнить smoke-тест.
3. Получить новую версию проекта и проверить Compose.
4. Пересоздать изменившиеся контейнеры.

```bash
git pull --ff-only
docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml config >/dev/null
docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml up -d
```

Тема и плагин подключены read-only из репозитория, поэтому production-обновление выполняется поставкой проверенной версии проекта, а не редактированием PHP через админку WordPress.

## 9. Откат

Храните предыдущий Git tag/commit и совместимую копию БД/uploads. При ошибке верните код на предыдущий проверенный tag, выполните `docker compose up -d` и, только если миграция несовместима, восстановите БД и uploads по утвержденной инструкции восстановления.
