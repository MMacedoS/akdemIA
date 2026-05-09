#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

APP_SERVICE="app"
WORKER_SERVICE="worker"
SCHEDULER_SERVICE="scheduler"
NGINX_SERVICE="nginx"
DB_SERVICE="db"
PHPMYADMIN_SERVICE="phpmyadmin"

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

ENV_LOADED="false"

if [[ $EUID -eq 0 ]]; then
  SUDO=""
  OWNER_USER="root"
  OWNER_GROUP="root"
  log "Executando como root."
else
  if ! command_exists sudo; then
    fail "sudo nao encontrado. Instale sudo e execute novamente."
  fi
  SUDO="sudo"
  OWNER_USER="$USER"
  OWNER_GROUP="$(id -gn)"
fi

if command_exists docker && docker info >/dev/null 2>&1; then
  DOCKER="docker"
elif [[ $EUID -eq 0 ]]; then
  DOCKER="docker"
else
  DOCKER="sudo docker"
fi

DC="$DOCKER compose"
HEALTH_WAIT_SECONDS="120"

install_docker_if_needed() {
  if command_exists docker && $DOCKER info >/dev/null 2>&1 && $DC version >/dev/null 2>&1; then
    log "Docker e Compose ja estao disponiveis."
    return
  fi

  log "Instalando Docker Engine e Compose Plugin..."
  $SUDO apt-get update -y
  $SUDO apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common

  $SUDO install -m 0755 -d /etc/apt/keyrings
  if [[ ! -f /etc/apt/keyrings/docker.gpg ]]; then
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | $SUDO gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    $SUDO chmod a+r /etc/apt/keyrings/docker.gpg
  fi

  local codename
  codename="$(. /etc/os-release && echo "$VERSION_CODENAME")"

  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu ${codename} stable" \
    | $SUDO tee /etc/apt/sources.list.d/docker.list >/dev/null

  $SUDO apt-get update -y
  $SUDO apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

  if [[ $EUID -ne 0 ]] && ! groups "$USER" | grep -q '\bdocker\b'; then
    log "Adicionando usuario ao grupo docker..."
    $SUDO usermod -aG docker "$USER"
    log "Abra uma nova sessao SSH apos este script para aplicar grupo docker sem sudo."
  fi

  if [[ $EUID -eq 0 ]]; then
    DOCKER="docker"
  else
    DOCKER="sudo docker"
  fi
  DC="$DOCKER compose"
}

prepare_env() {
  if [[ -f .env.production ]]; then
    log "Aplicando .env.production como .env"
    cp .env.production .env
  elif [[ ! -f .env ]]; then
    fail "Nenhum .env encontrado e .env.production tambem nao existe."
  fi

  grep -q '^APP_ENV=production' .env || log "Aviso: APP_ENV nao esta como production no .env"
}

load_env() {
  set -a
  # shellcheck disable=SC1091
  . ./.env
  set +a
  ENV_LOADED="true"
}

validate_env() {
  [[ "$ENV_LOADED" == "true" ]] || fail "Variaveis de ambiente ainda nao foram carregadas."

  [[ -n "${DB_CONNECTION:-}" ]] || fail "DB_CONNECTION nao definido no .env"
  [[ -n "${DB_HOST:-}" ]] || fail "DB_HOST nao definido no .env"
  [[ -n "${DB_PORT:-}" ]] || fail "DB_PORT nao definido no .env"
  [[ -n "${DB_DATABASE:-}" ]] || fail "DB_DATABASE nao definido no .env"
  [[ -n "${DB_USERNAME:-}" ]] || fail "DB_USERNAME nao definido no .env"
  [[ -n "${DB_PASSWORD:-}" ]] || fail "DB_PASSWORD nao definido no .env"
  [[ -n "${DB_ROOT_PASSWORD:-}" ]] || fail "DB_ROOT_PASSWORD nao definido no .env"

  if [[ "${DB_CONNECTION}" == "mysql" && "${DB_USERNAME}" == "root" ]]; then
    log "Aviso: DB_USERNAME=root nao e o cenario recomendado. O script vai tentar reparar o acesso usando a conta root existente."
  fi

  if [[ "${DB_USERNAME}" == "root" && "${DB_PASSWORD}" != "${DB_ROOT_PASSWORD}" ]]; then
    log "Aviso: DB_USERNAME=root com senha diferente de DB_ROOT_PASSWORD. O script vai tentar reparar automaticamente."
  fi
}

clear_local_artifacts() {
  log "Limpando caches locais para evitar erro de provider e cache stale"
  rm -f bootstrap/cache/*.php || true
  find storage/framework/cache/data -mindepth 1 ! -name '.gitignore' -delete 2>/dev/null || true
  find storage/framework/views -mindepth 1 ! -name '.gitignore' -delete 2>/dev/null || true
}

clear_container_runtime_cache() {
  log "Limpando caches persistidos nos volumes do container"
  $DC exec -T "$APP_SERVICE" sh -lc '
    mkdir -p \
      bootstrap/cache \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      storage/tmp

    find bootstrap/cache -maxdepth 1 -type f \( -name "*.php" -o -name "*.json" \) -delete 2>/dev/null || true
    find storage/framework/cache/data -mindepth 1 ! -name ".gitignore" -delete 2>/dev/null || true
    find storage/framework/views -mindepth 1 ! -name ".gitignore" -delete 2>/dev/null || true
  '
}

set_permissions() {
  log "Aplicando permissoes em storage e bootstrap/cache"
  mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
  $SUDO chown -R "$OWNER_USER":"$OWNER_GROUP" storage bootstrap/cache
  chmod -R ug+rwX storage bootstrap/cache
}

install_php_js_dependencies() {
  log "Instalando dependencias PHP (composer)"
  $DOCKER run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$PROJECT_DIR:/app" \
    -w /app \
    composer:2.8 \
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

  log "Instalando dependencias JS e gerando build (npm)"
  $DOCKER run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$PROJECT_DIR:/app" \
    -w /app \
    node:22-alpine \
    sh -lc "npm install && npm run build"
}

build_and_up() {
  log "Subindo stack Docker"
  $DC build --pull --no-cache
  $DC up -d "$DB_SERVICE" redis

  wait_for_service_health "$DB_SERVICE"
  wait_for_service_health "redis"

  $DC up -d "$APP_SERVICE" "$WORKER_SERVICE" "$SCHEDULER_SERVICE" "$NGINX_SERVICE" "$PHPMYADMIN_SERVICE"
}

print_service_logs() {
  local service="$1"

  log "Ultimas linhas de log de ${service}:"
  $DC logs --tail=120 "$service" || true
}

wait_for_service_health() {
  local service="$1"
  local elapsed=0
  local status=""
  local container_id=""

  while (( elapsed < HEALTH_WAIT_SECONDS )); do
    container_id="$($DC ps -q "$service" 2>/dev/null || true)"

    if [[ -z "$container_id" ]]; then
      log "Servico ${service} ainda sem container criado. Aguardando..."
      sleep 2
      elapsed=$((elapsed + 2))
      continue
    fi

    status="$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container_id" 2>/dev/null || true)"

    case "$status" in
      healthy|running)
        log "Servico ${service} esta pronto (${status})."
        return 0
        ;;
      starting|created|restarting|running)
        log "Aguardando ${service} ficar saudavel (${status})..."
        ;;
      unhealthy|exited|dead)
        print_service_logs "$service"
        fail "Servico ${service} falhou durante a inicializacao (${status})."
        ;;
      *)
        log "Aguardando ${service}; status atual: ${status:-desconhecido}"
        ;;
    esac

    sleep 2
    elapsed=$((elapsed + 2))
  done

  print_service_logs "$service"
  fail "Timeout aguardando o servico ${service} ficar saudavel."
}

repair_db_access() {
  log "Tentando autoreparo de acesso ao banco"

  $DC up -d "$DB_SERVICE"

  if [[ "${DB_USERNAME}" == "root" ]]; then
    log "Usuario da app esta como root. Tentando ajustar senha de root para o valor de DB_PASSWORD"
      if $DC exec -T "$DB_SERVICE" sh -lc "mysql -uroot -p\"$DB_ROOT_PASSWORD\" -e \"ALTER USER 'root'@'%' IDENTIFIED BY '$DB_PASSWORD'; FLUSH PRIVILEGES;\""; then
      log "Senha de root ajustada para a senha esperada pela app."
      return 0
    fi
  fi

  log "Criando/atualizando usuario da aplicacao e grants"
    $DC exec -T "$DB_SERVICE" sh -lc "mysql -uroot -p\"$DB_ROOT_PASSWORD\" -e \"CREATE DATABASE IF NOT EXISTS \\`$DB_DATABASE\\`; CREATE USER IF NOT EXISTS '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD'; ALTER USER '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD'; GRANT ALL PRIVILEGES ON \\`$DB_DATABASE\\`.* TO '$DB_USERNAME'@'%'; FLUSH PRIVILEGES;\""
}

run_laravel_tasks() {
  log "Rodando migracoes e otimizacao"
  clear_container_runtime_cache
  $DC exec -T "$APP_SERVICE" php artisan optimize:clear || true
  $DC exec -T "$APP_SERVICE" php artisan key:generate --force || true

  if ! $DC exec -T "$APP_SERVICE" php artisan migrate --force; then
    repair_db_access
    $DC exec -T "$APP_SERVICE" php artisan migrate --force
  fi

  $DC exec -T "$APP_SERVICE" php artisan storage:link || true
  $DC exec -T "$APP_SERVICE" php artisan optimize

  log "Reiniciando processamento de filas"
  $DC exec -T "$APP_SERVICE" php artisan queue:restart || true
}

ensure_runtime_services() {
  log "Garantindo servicos de app, worker, scheduler, nginx e phpMyAdmin"
  $DC up -d "$APP_SERVICE" "$WORKER_SERVICE" "$SCHEDULER_SERVICE" "$NGINX_SERVICE" "$PHPMYADMIN_SERVICE"

  if ! $DC ps --services --filter status=running | grep -qx "$WORKER_SERVICE"; then
    fail "Servico worker nao ficou em execucao."
  fi

  if ! $DC ps --services --filter status=running | grep -qx "$SCHEDULER_SERVICE"; then
    fail "Servico scheduler nao ficou em execucao."
  fi
}

show_summary() {
  log "Deploy concluido. Status dos containers:"
  $DC ps

  log "Ultimas linhas de log do app:"
  $DC logs --tail=40 "$APP_SERVICE" || true

  log "Ultimas linhas de log do worker:"
  $DC logs --tail=40 "$WORKER_SERVICE" || true

  cat <<'EOF'

Comandos uteis:
  docker compose logs -f app
  docker compose logs -f worker
  docker compose logs -f scheduler
  docker compose logs -f phpmyadmin
  docker compose ps

EOF

  if [[ -n "${PMA_ABSOLUTE_URI:-}" ]]; then
    log "phpMyAdmin disponivel em: ${PMA_ABSOLUTE_URI}"
    if [[ -n "${PHPMYADMIN_PORT:-}" ]]; then
      log "Acesse com tunel SSH: ssh -L ${PHPMYADMIN_PORT}:127.0.0.1:${PHPMYADMIN_PORT} usuario@IP_DA_VPS"
    fi
  fi
}

main() {
  install_docker_if_needed
  prepare_env
  load_env
  validate_env
  clear_local_artifacts
  set_permissions
  install_php_js_dependencies
  build_and_up
  run_laravel_tasks
  ensure_runtime_services
  show_summary
}

main "$@"
