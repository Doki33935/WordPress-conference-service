#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
env_file="${1:-${script_dir}/.env.production}"
backup_dir="${BACKUP_DIR:-${script_dir}/backups}"
retention_days="${BACKUP_RETENTION_DAYS:-14}"

if [[ ! -f "${env_file}" ]]; then
  echo "Production env file not found: ${env_file}" >&2
  exit 1
fi

mkdir -p "${backup_dir}"
chmod 700 "${backup_dir}"
umask 077

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
compose=(docker compose --env-file "${env_file}" -f "${script_dir}/docker-compose.prod.yml")

"${compose[@]}" exec -T db sh -c 'exec mariadb-dump --single-transaction --quick --lock-tables=false -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
  | gzip -9 > "${backup_dir}/database-${timestamp}.sql.gz"

"${compose[@]}" exec -T wordpress sh -c 'if [ -d /var/www/html/wp-content/uploads ]; then exec tar -czf - -C /var/www/html/wp-content uploads; else exec tar -czf - --files-from /dev/null; fi' \
  > "${backup_dir}/uploads-${timestamp}.tar.gz"

find "${backup_dir}" -maxdepth 1 -type f \( -name 'database-*.sql.gz' -o -name 'uploads-*.tar.gz' \) -mtime "+${retention_days}" -delete

echo "Backup created in ${backup_dir}: ${timestamp}"
