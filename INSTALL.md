# Установка WordPress Conference Service

Проект состоит из WordPress, MariaDB/MySQL, темы `Fyremezzonine WP Theme` и плагина `Fyremezzonine Conference Manager`. Отдельный backend, Node.js, Python, Redis и внешний сервис двухфакторной аутентификации не требуются.

Поддерживаемое окружение:

- WordPress `6.9.x`;
- PHP `8.2` или `8.3` с расширениями `mysqli` и `openssl`;
- MariaDB `11.4.x` либо совместимая MySQL;
- исходящая почта через SMTP для подтверждения заявок.

Перед промышленным запуском также прочитайте `PRODUCTION_READINESS.md`.

## Состав поставки

- `outputs/fyremezzonine-wp-theme.zip` - устанавливаемая тема сайта;
- `outputs/fyremezzonine-conference-manager.zip` - плагин конференций, заявок, партнеров, ролей и email-подтверждения;
- `work/docker-compose.yml` - локальный Docker-стенд;
- `deploy/docker-compose.prod.yml` - production-стек WordPress, MariaDB, Caddy и WP-Cron;
- `deploy/backup.sh` - резервное копирование БД и uploads;
- `deploy/VM_DEPLOY.md` - отдельная инструкция для виртуальной машины.

## Способ 1. Локальная установка через Docker

Подходит для разработки, демонстрации и приемочного тестирования.

### Требования

1. Установить Docker Desktop.
2. Включить Docker Engine и поддержку `docker compose`.
3. Скачать или клонировать проект.

### Первый запуск

В PowerShell из корня проекта:

```powershell
Copy-Item work/.env.example work/.env
docker compose -f work/docker-compose.yml pull
docker compose -f work/docker-compose.yml up -d
docker compose -f work/docker-compose.yml ps
```

После запуска открыть:

- сайт: `http://localhost:8080/`;
- админку: `http://localhost:8080/wp-admin/`.

При первом пустом запуске пройти мастер WordPress, создать администратора, затем активировать тему и плагин. Исходники темы и плагина уже подключены в контейнер из каталога `outputs`.

SMTP необязателен для просмотра сайта. Для проверки писем заполните `work/.env` перед запуском контейнеров и не добавляйте этот файл в Git.

### Перезапуск и обновление контейнеров

Обычный перезапуск:

```powershell
docker compose -f work/docker-compose.yml restart
```

Пересоздание на версиях образов, закрепленных в Compose:

```powershell
docker compose -f work/docker-compose.yml pull
docker compose -f work/docker-compose.yml up -d --force-recreate
```

Именованные тома `wordpress_data` и `db_data` при этом сохраняются. Не используйте `docker compose down -v`, если нужно сохранить сайт, БД, пользователей и uploads.

Остановка стенда без удаления данных:

```powershell
docker compose -f work/docker-compose.yml down
```

## Способ 2. Установка ZIP-архивов в готовый WordPress

Подходит, если хостинг или виртуальная машина уже предоставляет WordPress и MySQL/MariaDB.

1. Установить WordPress `6.9.x` и подключить его к БД.
2. Войти в `/wp-admin/` под администратором.
3. Открыть `Внешний вид -> Темы -> Добавить новую -> Загрузить тему`.
4. Загрузить `outputs/fyremezzonine-wp-theme.zip` и активировать тему.
5. Открыть `Плагины -> Добавить новый -> Загрузить плагин`.
6. Загрузить `outputs/fyremezzonine-conference-manager.zip` и активировать плагин.
7. Открыть `Настройки -> Постоянные ссылки` и нажать `Сохранить изменения`.
8. Проверить наличие страниц:
   - `/registration/` со shortcode `[conference_registration_form]`;
   - `/registration/verify/` для ввода email-кода;
   - `/partnership/` со shortcode `[conference_partner_request_form]`;
   - `/editor/conferences/` со shortcode `[conference_submission_form]`.
9. Назначить роли: `Администратор` системному владельцу, `Редактор` или `Автор` контент-менеджерам, `Ответственный за секции` сотрудникам со статистикой закрепленной секции.
10. В разделе `Редактор -> Конференции` создать конференцию, проверить предпросмотр и опубликовать ее.

При активации плагин самостоятельно создает и обновляет свои таблицы через WordPress `dbDelta`. Отдельно создавать структуру БД вручную не требуется.

## Способ 3. Ручная установка файлов

Используется, если загрузка ZIP через админку запрещена ограничением хостинга.

1. Распаковать тему в `wp-content/themes/fyremezzonine-wp-theme/`.
2. Распаковать плагин в `wp-content/plugins/fyremezzonine-conference-manager/`.
3. Убедиться, что веб-сервер может читать файлы, но не выдать им права `777`.
4. Активировать тему и плагин через `/wp-admin/`.
5. Сохранить постоянные ссылки и выполнить пункты 8-10 из способа 2.

Не заменяйте весь каталог `wp-content`: в нем находятся uploads и другие компоненты конкретного сайта.

## Способ 4. Перенос готового сайта из резервной копии

Этот вариант переносит не только код, но и конференции, настройки, пользователей и загруженные файлы. Нужны совместимые архивы БД и `uploads`.

1. Развернуть чистый WordPress и MariaDB/MySQL.
2. Установить ту же версию темы и плагина.
3. Создать резервную копию нового стенда перед восстановлением.
4. Импортировать SQL в БД WordPress.
5. Восстановить `wp-content/uploads`.
6. Если адрес сайта изменился, безопасно заменить старый URL на новый средствами WP-CLI или миграционного инструмента с поддержкой сериализованных данных.
7. Сохранить постоянные ссылки и пройти проверку после установки.

Для production-стека архивы, созданные `deploy/backup.sh`, восстанавливаются так:

```bash
gunzip -c deploy/backups/database-YYYYMMDDTHHMMSSZ.sql.gz | \
  docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml exec -T db \
  sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'

cat deploy/backups/uploads-YYYYMMDDTHHMMSSZ.tar.gz | \
  docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml exec -T wordpress \
  sh -c 'rm -rf /var/www/html/wp-content/uploads && tar -xzf - -C /var/www/html/wp-content'
```

Восстановление сначала выполняйте на отдельном тестовом стенде. Команда восстановления uploads заменяет текущую папку `uploads`.

## Способ 5. Установка на чистую виртуальную машину

Для Ubuntu `22.04/24.04 LTS` подготовлен production-стек с Caddy, HTTPS, отдельным WP-Cron и MariaDB:

```bash
sudo bash deploy/install-docker-ubuntu.sh
cp deploy/.env.production.example deploy/.env.production
nano deploy/.env.production
docker compose --env-file deploy/.env.production \
  -f deploy/docker-compose.prod.yml up -d
```

Полная последовательность, требования к firewall, резервным копиям и обновлениям описаны в `deploy/VM_DEPLOY.md`. Домен можно указать позже перед production-запуском; для текущего локального стенда он не требуется.

## Настройка почты

Плагин отправляет участнику шестизначный код после регистрации. До подтверждения email заявка имеет статус `pending_email` и не попадает в рабочую статистику, печать и Excel.

Администратор настраивает почту в разделе:

```text
Конференции -> Почта
```

Там можно изменить SMTP-сервер, порт, шифрование, логин, пароль, адрес и имя отправителя, а затем отправить тестовое письмо. Если почтовый сервис блокирует вход по IP, администратор почтового домена должен разрешить IP сервера или выпустить пароль приложения.

Переменные окружения для Docker или сервера:

```text
FYREMEZZONINE_SMTP_HOST=smtp.example.ru
FYREMEZZONINE_SMTP_PORT=587
FYREMEZZONINE_SMTP_ENCRYPTION=tls
FYREMEZZONINE_SMTP_USERNAME=noreply@example.ru
FYREMEZZONINE_SMTP_PASSWORD=<пароль приложения SMTP>
FYREMEZZONINE_SMTP_FROM_EMAIL=noreply@example.ru
FYREMEZZONINE_SMTP_FROM_NAME=ВНИИПО Конференции
```

SMTP-пароль нельзя хранить в Git, README, ZIP-архивах темы или плагина. Пароль, сохраненный через CMS, шифруется AES-256-GCM с ключами WordPress.

## Проверка после установки

1. Открыть главную страницу и `/wp-admin/`.
2. Проверить создание, предпросмотр, публикацию, снятие с публикации и завершение конференции.
3. Проверить роли администратора, редактора и ответственного за секцию.
4. Отправить тестовую заявку на реальный email и ввести код на `/registration/verify/`.
5. Убедиться, что после подтверждения показаны успешная регистрация и ссылка на чат.
6. Проверить заявки на участие, партнерство, фильтры, отметку прибытия, печать и Excel-выгрузку.
7. Открыть `Инструменты -> Здоровье сайта` и проверить отсутствие критических ошибок.
8. Создать свежий backup БД и uploads и выполнить пробное восстановление на отдельном стенде.

## Обновление установленного сайта

1. Создать резервную копию БД и `wp-content/uploads`.
2. Проверить обновление на тестовом стенде.
3. Загрузить новую версию ZIP-плагина и подтвердить замену старой версии.
4. Загрузить новую версию ZIP-темы и подтвердить замену.
5. Открыть сайт и выполнить проверку после установки.

На Docker-сервере обновление выполняется получением проверенной версии проекта и командой `docker compose up -d`; PHP-файлы темы и плагина через редактор WordPress на production не изменяются.
