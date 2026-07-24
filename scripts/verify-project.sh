#!/usr/bin/env bash
set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_dir="${repo_dir}/outputs/fyremezzonine-conference-manager"
theme_dir="${repo_dir}/outputs/fyremezzonine-wp-theme"

find "${plugin_dir}" "${theme_dir}" -type f -name '*.php' -print0 \
  | xargs -0 -n1 php -l

node --check "${theme_dir}/assets/theme-effects.js"
bash -n "${repo_dir}"/deploy/*.sh "${repo_dir}"/scripts/*.sh

if grep -RInE --exclude='*.zip' --exclude='*.pptx' --exclude='*.ndjson' \
  --exclude='.env' --exclude='.env.production' \
  '(BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|FYREMEZZONINE_SMTP_PASSWORD=[^<[:space:]$%{]|rJY_)' \
  "${repo_dir}/README.md" "${repo_dir}/INSTALL.md" "${repo_dir}/PRODUCTION_READINESS.md" \
  "${repo_dir}/outputs" "${repo_dir}/deploy" "${repo_dir}/work" "${repo_dir}/.github"; then
  echo 'Potential secret found in tracked project files.' >&2
  exit 1
fi

plugin_version="$(sed -n "s/^ \* Version: //p" "${plugin_dir}/fyremezzonine-conference-manager.php" | head -n1)"
constant_version="$(sed -n "s/define('FYREMEZZONINE_MANAGER_VERSION', '\([^']*\)');/\1/p" "${plugin_dir}/fyremezzonine-conference-manager.php" | head -n1)"
if [[ -z "${plugin_version}" || "${plugin_version}" != "${constant_version}" ]]; then
  echo "Plugin version mismatch: header=${plugin_version}, constant=${constant_version}" >&2
  exit 1
fi

docker compose --env-file "${repo_dir}/deploy/.env.production.example" \
  -f "${repo_dir}/deploy/docker-compose.prod.yml" config >/dev/null

echo 'Static project checks passed.'
