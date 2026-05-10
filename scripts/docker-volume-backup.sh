#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

BACKUP_ROOT="${BACKUP_ROOT:-$HOME/akdemia-backups}"
STORAGE_VOLUME_LOGICAL="${STORAGE_VOLUME_LOGICAL:-app_storage}"
BOOTSTRAP_CACHE_VOLUME_LOGICAL="${BOOTSTRAP_CACHE_VOLUME_LOGICAL:-app_bootstrap_cache}"
INCLUDE_BOOTSTRAP_CACHE="${INCLUDE_BOOTSTRAP_CACHE:-true}"

ACTION="${1:-}"
if [[ $# -gt 0 ]]; then
  shift
fi

RESTORE_INPUT_DIR=""
RESTORE_FORCE="false"

log() {
  printf "\n[%s] %s\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
  printf "\n[ERRO] %s\n" "$*" >&2
  exit 1
}

command_exists() {
  command -v "$1" >/dev/null 2>&1
}

usage() {
  cat <<'EOF'
Uso:
  bash scripts/docker-volume-backup.sh backup
  bash scripts/docker-volume-backup.sh list
  bash scripts/docker-volume-backup.sh restore --input-dir CAMINHO [--force]

Variaveis opcionais:
  BACKUP_ROOT=$HOME/akdemia-backups
  STORAGE_VOLUME_LOGICAL=app_storage
  BOOTSTRAP_CACHE_VOLUME_LOGICAL=app_bootstrap_cache
  INCLUDE_BOOTSTRAP_CACHE=true|false

Exemplos:
  bash scripts/docker-volume-backup.sh backup
  bash scripts/docker-volume-backup.sh list
  bash scripts/docker-volume-backup.sh restore --input-dir "$HOME/akdemia-backups/20260510-153000" --force
EOF
}

if [[ $EUID -eq 0 ]]; then
  DOCKER="docker"
else
  if command_exists docker && docker info >/dev/null 2>&1; then
    DOCKER="docker"
  elif command_exists sudo; then
    DOCKER="sudo docker"
  else
    fail "docker indisponivel para o usuario atual e sudo nao encontrado."
  fi
fi

DC="$DOCKER compose"

ensure_requirements() {
  command_exists docker || fail "docker nao encontrado."
  $DOCKER info >/dev/null 2>&1 || fail "docker daemon indisponivel."
  $DC version >/dev/null 2>&1 || fail "docker compose nao disponivel."
  mkdir -p "$BACKUP_ROOT"
}

parse_restore_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --input-dir)
        RESTORE_INPUT_DIR="${2:-}"
        shift
        ;;
      --force)
        RESTORE_FORCE="true"
        ;;
      --help|-h)
        usage
        exit 0
        ;;
      *)
        fail "Opcao invalida no restore: $1"
        ;;
    esac

    shift
  done

  [[ -n "$RESTORE_INPUT_DIR" ]] || fail "Informe --input-dir para restaurar."
  [[ -d "$RESTORE_INPUT_DIR" ]] || fail "Diretorio de backup nao encontrado: $RESTORE_INPUT_DIR"
}

resolve_volume_name() {
  local logical_name="$1"
  local volume_name=""

  volume_name="$($DOCKER volume ls \
    --filter "label=com.docker.compose.volume=${logical_name}" \
    --format '{{.Name}}' | head -n 1)"

  if [[ -z "$volume_name" ]]; then
    fail "Volume nao encontrado para o nome logico ${logical_name}. Suba a stack primeiro com bash scripts/deploy-ubuntu.sh ou confira os labels do Docker Compose."
  fi

  printf '%s' "$volume_name"
}

backup_volume() {
  local logical_name="$1"
  local archive_name="$2"
  local output_dir="$3"
  local volume_name=""

  volume_name="$(resolve_volume_name "$logical_name")"
  log "Gerando backup do volume ${volume_name} em ${archive_name}"

  $DOCKER run --rm \
    -v "${volume_name}:/from:ro" \
    -v "${output_dir}:/to" \
    alpine sh -lc "cd /from && tar -czf /to/${archive_name} ."
}

restore_volume() {
  local logical_name="$1"
  local archive_name="$2"
  local input_dir="$3"
  local volume_name=""

  volume_name="$(resolve_volume_name "$logical_name")"

  [[ -f "${input_dir}/${archive_name}" ]] || fail "Arquivo de backup nao encontrado: ${input_dir}/${archive_name}"

  log "Restaurando ${archive_name} no volume ${volume_name}"

  $DOCKER run --rm \
    -v "${volume_name}:/to" \
    -v "${input_dir}:/from:ro" \
    -e RESTORE_FORCE="$RESTORE_FORCE" \
    -e ARCHIVE_NAME="$archive_name" \
    alpine sh -lc '
      set -eu

      if [ "$RESTORE_FORCE" != "true" ] && find /to -mindepth 1 -print -quit | grep -q .; then
        echo "Volume de destino nao esta vazio. Use --force para sobrescrever." >&2
        exit 1
      fi

      if [ "$RESTORE_FORCE" = "true" ]; then
        find /to -mindepth 1 -maxdepth 1 -exec rm -rf {} +
      fi

      tar -xzf "/from/${ARCHIVE_NAME}" -C /to
    '
}

write_manifest() {
  local output_dir="$1"
  local storage_volume_name="$2"
  local bootstrap_cache_volume_name="$3"

  cat > "${output_dir}/manifest.txt" <<EOF
created_at=$(date '+%Y-%m-%d %H:%M:%S')
hostname=$(hostname)
project_dir=${PROJECT_DIR}
storage_volume_logical=${STORAGE_VOLUME_LOGICAL}
storage_volume_real=${storage_volume_name}
bootstrap_cache_volume_logical=${BOOTSTRAP_CACHE_VOLUME_LOGICAL}
bootstrap_cache_volume_real=${bootstrap_cache_volume_name}
include_bootstrap_cache=${INCLUDE_BOOTSTRAP_CACHE}
EOF

  if command_exists sha256sum; then
    (
      cd "$output_dir"
      sha256sum ./*.tar.gz > checksums.sha256
    )
  fi
}

run_backup() {
  local timestamp=""
  local output_dir=""
  local storage_volume_name=""
  local bootstrap_cache_volume_name=""

  timestamp="$(date '+%Y%m%d-%H%M%S')"
  output_dir="${BACKUP_ROOT}/${timestamp}"
  mkdir -p "$output_dir"

  storage_volume_name="$(resolve_volume_name "$STORAGE_VOLUME_LOGICAL")"
  backup_volume "$STORAGE_VOLUME_LOGICAL" "app_storage.tar.gz" "$output_dir"

  if [[ "$INCLUDE_BOOTSTRAP_CACHE" == "true" ]]; then
    bootstrap_cache_volume_name="$(resolve_volume_name "$BOOTSTRAP_CACHE_VOLUME_LOGICAL")"
    backup_volume "$BOOTSTRAP_CACHE_VOLUME_LOGICAL" "app_bootstrap_cache.tar.gz" "$output_dir"
  fi

  write_manifest "$output_dir" "$storage_volume_name" "$bootstrap_cache_volume_name"

  log "Backup concluido em: $output_dir"
  ls -lah "$output_dir"
}

run_restore() {
  parse_restore_args "$@"

  restore_volume "$STORAGE_VOLUME_LOGICAL" "app_storage.tar.gz" "$RESTORE_INPUT_DIR"

  if [[ "$INCLUDE_BOOTSTRAP_CACHE" == "true" ]] && [[ -f "${RESTORE_INPUT_DIR}/app_bootstrap_cache.tar.gz" ]]; then
    restore_volume "$BOOTSTRAP_CACHE_VOLUME_LOGICAL" "app_bootstrap_cache.tar.gz" "$RESTORE_INPUT_DIR"
  fi

  log "Restore concluido."
  cat <<'EOF'

Validacoes sugeridas:
  docker compose exec -T app sh -lc 'ls -lah /var/www/storage'
  docker compose exec -T app sh -lc 'find /var/www/storage/app -maxdepth 3 -type f | head -n 50'
  docker compose exec -T app php artisan storage:link || true
  docker compose exec -T app php artisan optimize:clear || true

EOF
}

run_list() {
  log "Backups disponiveis em ${BACKUP_ROOT}:"
  ls -lah "$BACKUP_ROOT"
}

main() {
  ensure_requirements

  case "$ACTION" in
    backup)
      run_backup
      ;;
    restore)
      run_restore "$@"
      ;;
    list)
      run_list
      ;;
    --help|-h|"")
      usage
      ;;
    *)
      fail "Acao invalida: $ACTION"
      ;;
  esac
}

main "$@"

para pegar o backup mais recente e restaurar, voce pode usar:

bash scripts/docker-volume-backup.sh backup

docker compose down

mkdir -p storage bootstrap/cache

STORAGE_VOL="$(docker volume ls --filter label=com.docker.compose.volume=app_storage --format '{{.Name}}' | head -n 1)"
BOOTSTRAP_VOL="$(docker volume ls --filter label=com.docker.compose.volume=app_bootstrap_cache --format '{{.Name}}' | head -n 1)"

docker run --rm \
  -v "${STORAGE_VOL}:/from:ro" \
  -v "$PWD/storage:/to" \
  alpine sh -lc 'cp -a /from/. /to/'

docker run --rm \
  -v "${BOOTSTRAP_VOL}:/from:ro" \
  -v "$PWD/bootstrap/cache:/to" \
  alpine sh -lc 'cp -a /from/. /to/'
