#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

COMPOSE_FILE="docker-compose.local.yml"
DC="docker compose -f ${COMPOSE_FILE}"
APP_SERVICE="app"
DB_SERVICE="db"
REDIS_SERVICE="redis"
WORKER_SERVICE="worker"
SCHEDULER_SERVICE="scheduler"
NGINX_SERVICE="nginx"
PHPMYADMIN_SERVICE="phpmyadmin"
HEALTH_WAIT_SECONDS="120"
DOCKER_INFO_LOG=""
TEMP_DOCKER_CONFIG_DIR=""

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

cleanup() {
  if [[ -n "$TEMP_DOCKER_CONFIG_DIR" ]] && [[ -d "$TEMP_DOCKER_CONFIG_DIR" ]]; then
    rm -rf "$TEMP_DOCKER_CONFIG_DIR"
  fi
}

trap cleanup EXIT

configure_docker_endpoint() {
  local default_info_log=""

  DOCKER_INFO_LOG="$(docker info 2>&1 || true)"
  if [[ "$DOCKER_INFO_LOG" != *"Cannot connect to the Docker daemon"* ]] && [[ "$DOCKER_INFO_LOG" != *"permission denied while trying to connect"* ]]; then
    return 0
  fi

  default_info_log="$(docker --context default info 2>&1 || true)"
  if [[ "$default_info_log" != *"Cannot connect to the Docker daemon"* ]] && [[ "$default_info_log" != *"permission denied while trying to connect"* ]]; then
    export DOCKER_HOST="unix:///var/run/docker.sock"
    DOCKER_INFO_LOG="$default_info_log"
    return 0
  fi

  if [[ "$default_info_log" == *"permission denied while trying to connect"* ]]; then
    fail "sem permissao no daemon Docker em /var/run/docker.sock. Adicione o usuario ao grupo docker e reabra a sessao: sudo usermod -aG docker $USER"
  fi

  if [[ "$DOCKER_INFO_LOG" == *"/home/"*"/.docker/desktop/docker.sock"* ]]; then
    fail "o contexto ativo do Docker aponta para Docker Desktop, mas esse daemon nao esta disponivel. Inicie o Docker Desktop ou execute: docker context use default"
  fi

  fail "docker daemon indisponivel. Verifique se o servico Docker esta em execucao."
}

configure_docker_auth() {
  local docker_config_file="${DOCKER_CONFIG:-$HOME/.docker}/config.json"

  if ! [[ -f "$docker_config_file" ]]; then
    return 0
  fi

  if ! grep -q '"credsStore"[[:space:]]*:[[:space:]]*"desktop"' "$docker_config_file"; then
    return 0
  fi

  if command_exists docker-credential-desktop; then
    return 0
  fi

  TEMP_DOCKER_CONFIG_DIR="$(mktemp -d)"
  printf '{\n  "auths": {}\n}\n' > "$TEMP_DOCKER_CONFIG_DIR/config.json"
  export DOCKER_CONFIG="$TEMP_DOCKER_CONFIG_DIR"
  log "Usando configuracao Docker temporaria sem docker-credential-desktop para builds locais"
}

ensure_requirements() {
  command_exists docker || fail "docker nao encontrado."
  configure_docker_endpoint
  configure_docker_auth
  $DC version >/dev/null 2>&1 || fail "docker compose nao disponivel."
  [[ -f .env ]] || fail ".env nao encontrado em ${PROJECT_DIR}."
}

install_php_dependencies() {
  if [[ -d vendor ]]; then
    log "Dependencias PHP ja existem em vendor/."
    return
  fi

  log "Instalando dependencias PHP via container Composer"
  docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$PROJECT_DIR:/app" \
    -w /app \
    composer:2.8 \
    composer install --no-interaction --prefer-dist --optimize-autoloader
}

install_js_dependencies() {
  if [[ -f public/build/manifest.json ]]; then
    log "Build frontend ja existe em public/build/."
    return
  fi

  log "Instalando dependencias JS e gerando build local"
  docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$PROJECT_DIR:/app" \
    -w /app \
    node:22-alpine \
    sh -lc "npm ci && npm run build"
}

wait_for_service_health() {
  local service="$1"
  local elapsed=0
  local status=""
  local container_id=""

  while (( elapsed < HEALTH_WAIT_SECONDS )); do
    container_id="$($DC ps -q "$service" 2>/dev/null || true)"

    if [[ -z "$container_id" ]]; then
      sleep 2
      elapsed=$((elapsed + 2))
      continue
    fi

    status="$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container_id" 2>/dev/null || true)"

    case "$status" in
      healthy|running)
        return 0
        ;;
      unhealthy|exited|dead)
        $DC logs --tail=120 "$service" || true
        fail "Servico ${service} falhou durante a inicializacao (${status})."
        ;;
    esac

    sleep 2
    elapsed=$((elapsed + 2))
  done

  $DC logs --tail=120 "$service" || true
  fail "Timeout aguardando ${service} ficar pronto."
}

up_stack() {
  log "Subindo banco e redis locais"
  $DC up -d --build "$DB_SERVICE" "$REDIS_SERVICE"
  wait_for_service_health "$DB_SERVICE"
  wait_for_service_health "$REDIS_SERVICE"

  log "Subindo aplicacao local"
  $DC up -d --build "$APP_SERVICE" "$WORKER_SERVICE" "$SCHEDULER_SERVICE" "$NGINX_SERVICE" "$PHPMYADMIN_SERVICE"
  wait_for_service_health "$APP_SERVICE"
}

run_laravel_tasks() {
  log "Executando tarefas Laravel no container local"
  $DC exec -T "$APP_SERVICE" php artisan optimize:clear || true
  $DC exec -T "$APP_SERVICE" php artisan storage:link || true
  $DC exec -T "$APP_SERVICE" php artisan migrate --force
  $DC exec -T "$APP_SERVICE" php artisan about
}

show_summary() {
  log "Containers locais ativos"
  $DC ps

  cat <<'EOF'

Ambiente local pronto:
  App:       http://akademia.localhost:8080
  phpMyAdmin: http://127.0.0.1:8081
  MySQL host: 127.0.0.1:3307
  Redis host: 127.0.0.1:6380

Comandos uteis:
  docker compose -f docker-compose.local.yml logs -f app
  docker compose -f docker-compose.local.yml logs -f nginx
  docker compose -f docker-compose.local.yml exec app bash

EOF
}

main() {
  ensure_requirements
  install_php_dependencies
  install_js_dependencies
  up_stack
  run_laravel_tasks
  show_summary
}

main "$@"
