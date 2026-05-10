# AcademAI

AcademAI e uma plataforma multi-tenant para gestao de operacoes fitness, com autenticacao, painel administrativo, landing pages publicas, gestao de alunos, treinadores e trainees, geracao de treinos com apoio de IA, controle de creditos e configuracoes globais por tenant e por sistema.

O projeto foi construido com Laravel 13, interface web em Blade, assets compilados com Vite e Tailwind CSS, API versionada em `/api/v1` e fluxo local baseado em Docker.

## Visao Geral

O sistema esta organizado em duas frentes principais:

- Web: dashboards, autenticacao, landing pages, selecao de tenant, administracao e experiencia do aluno.
- API: autenticacao, assinatura, perfil, preferencias, dados fisicos e medicos, treino, uso administrativo e logs de IA.

Perfis e contextos principais:

- System Admin: administracao global da plataforma, tenants, trainees, creditos e configuracoes de email, pagamento e WorkoutX.
- Admin de tenant: administracao do tenant, usuarios, alunos, trainees, treinadores, landing page do tenant e creditos.
- Trainer: gestao de alunos e operacoes de treino.
- Trainee: gestao de alunos, solicitacao de creditos e geracao de treinos.
- Student: dashboard, dados de saude e acesso ao treino ativo.

## Skills e Tecnologias Utilizadas

Skills tecnicas aplicadas no projeto:

- Laravel 13 para backend, roteamento, filas, middlewares, service container e validacao.
- PHP 8.4 no ambiente Docker local.
- Laravel Fortify para autenticacao.
- Arquitetura multi-tenant com resolucao de tenant por dominio, sessao e contexto autenticado.
- Blade para renderizacao do frontend.
- Vite para build de assets.
- Tailwind CSS para estilos.
- MySQL para persistencia principal.
- Redis para fila e suporte a processos assincros no ambiente containerizado.
- Queue worker e scheduler dedicados no ambiente Docker local.
- PHPUnit para testes.
- Laravel Pint para padronizacao de codigo PHP.
- Prettier para formatacao dos assets em resources.

Skills funcionais implementadas:

- Gestao multi-tenant de usuarios e acessos.
- Landing pages publicas por sistema, usuario e subdominio de tenant.
- Fluxo de geracao de treinos.
- Gestao de creditos.
- Gestao de dados fisicos, medicos e preferencias.
- Configuracoes globais de email, pagamento e integracoes.
- Notificacoes e comunicacao com usuarios.

## Estrutura do Projeto

Resumo da estrutura principal:

```text
akdemIA/
├── app/
│   ├── Actions/Fortify/
│   ├── Concerns/
│   ├── Console/Commands/
│   ├── Enums/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/
│   │   │   └── Web/V1/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Jobs/
│   ├── Models/
│   │   ├── AI/
│   │   ├── Credits/
│   │   ├── Landing/
│   │   ├── MedicalData/
│   │   ├── PhysicalData/
│   │   ├── Preferences/
│   │   ├── Tenant/
│   │   └── Workout/
│   ├── Notifications/
│   ├── Observers/
│   ├── Providers/
│   ├── Repositories/
│   ├── Services/
│   ├── Support/
│   └── Transformers/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docker/
│   ├── nginx/
│   └── php/
├── docs/
├── lang/
├── postman/
├── public/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
├── routes/
├── scripts/
├── storage/
├── tests/
├── Dockerfile
├── docker-compose.local.yml
├── composer.json
├── package.json
└── vite.config.ts
```

Descricao por area:

- `app/Actions/Fortify`: acoes de autenticacao e cadastro.
- `app/Http/Controllers/Api/V1`: endpoints da API versionada.
- `app/Http/Controllers/Web/V1`: controladores da interface web.
- `app/Http/Middleware`: identificacao de tenant, papeis e regras de acesso.
- `app/Jobs`: rotinas assincornas e processamento em fila.
- `app/Models`: modelos por dominio de negocio.
- `app/Repositories`: abstracoes de persistencia por contexto.
- `app/Services`: regras de negocio da aplicacao.
- `app/Support`: helpers e componentes de apoio.
- `config`: configuracoes do framework e da aplicacao.
- `database/migrations`: evolucao do schema.
- `docker`: artefatos do ambiente containerizado.
- `resources/views`: telas Blade do sistema.
- `routes/web.php`: fluxo web principal.
- `routes/api.php`: API REST em `/api/v1`.
- `scripts/deploy-ubuntu.sh`: fluxo de deploy de producao.
- `scripts/docker-volume-backup.sh`: backup e restore dos volumes nomeados do Docker em producao.
- `scripts/deploy-local-docker.sh`: fluxo local baseado em Docker.

## Modulos Principais

### Web

- Landing publica do sistema.
- Landing publica por usuario em `/pro/{slug}`.
- Landing publica por subdominio de tenant.
- Login Fortify e login de system admin.
- Dashboard por perfil.
- Cadastro e gerenciamento de usuarios, alunos, trainees e trainers.
- Gestao de landing page do tenant.
- Gestao de creditos e solicitacoes.
- Configuracoes globais de pagamento, email e WorkoutX.

### API

- `/api/v1/auth`: login e selecao de tenant.
- `/api/v1/billing`: assinaturas e webhook Stripe.
- `/api/v1/me`: perfil do usuario autenticado.
- `/api/v1/physical-data`: dados fisicos.
- `/api/v1/medical-data`: dados medicos.
- `/api/v1/preferences`: preferencias do usuario.
- `/api/v1/workouts`: geracao, status e consulta de exercicios.
- `/api/v1/admin`: dashboard, logs de IA, uso e comunicacao.

## Requisitos Para Rodar Localmente

- Docker Engine com permissao para o usuario atual.
- Docker Compose v2.
- Porta `8080` livre para a aplicacao.
- Porta `8081` livre para phpMyAdmin.
- Porta `3307` livre para MySQL local do projeto.
- Porta `6380` livre para Redis local do projeto.
- Arquivo `.env` presente na raiz do projeto.

Observacao importante:

- O ambiente local Docker deste projeto usa PHP 8.4 na imagem da aplicacao.
- O script local tenta contornar configuracoes quebradas do Docker Desktop quando o helper `docker-credential-desktop` nao existe.

## Como Rodar No Local

### Fluxo principal com Docker

Na raiz do projeto:

```bash
bash scripts/deploy-local-docker.sh
```

Esse script faz o seguinte:

- valida Docker e Compose
- trata contexto Docker local quando necessario
- instala dependencias PHP se `vendor/` nao existir
- instala dependencias frontend e gera build se `public/build/manifest.json` nao existir
- sobe MySQL e Redis
- sobe app, worker, scheduler, nginx e phpMyAdmin
- limpa cache compilado local antes do bootstrap Laravel
- executa `optimize:clear`, `storage:link`, `migrate --force` e `about`

### Endpoints locais

- Aplicacao: `http://akademia.localhost:8080`
- phpMyAdmin: `http://127.0.0.1:8081`
- MySQL: `127.0.0.1:3307`
- Redis: `127.0.0.1:6380`

### Comandos uteis do ambiente local

```bash
docker compose -f docker-compose.local.yml ps
docker compose -f docker-compose.local.yml logs -f app
docker compose -f docker-compose.local.yml logs -f nginx
docker compose -f docker-compose.local.yml logs -f worker
docker compose -f docker-compose.local.yml logs -f scheduler
docker compose -f docker-compose.local.yml exec app bash
docker compose -f docker-compose.local.yml down
docker compose -f docker-compose.local.yml down -v
docker compose -f docker-compose.local.yml build --no-cache app worker scheduler nginx
```

### Quando precisar rebuildar totalmente

```bash
docker compose -f docker-compose.local.yml down
docker compose -f docker-compose.local.yml build --no-cache app worker scheduler nginx
bash scripts/deploy-local-docker.sh
```

## Comandos de Desenvolvimento Fora do Docker

Se voce quiser executar partes isoladas no host:

```bash
composer install
npm install
npm run build
php artisan optimize:clear
php artisan migrate
php artisan about
php artisan test
composer run lint
composer run lint:check
```

Observacao:

- Para execucao fora do Docker, o `.env` precisa apontar para servicos acessiveis no host.
- O fluxo principal recomendado para este projeto e o Docker local em `scripts/deploy-local-docker.sh`.

## Qualidade e Testes

Comandos disponiveis:

```bash
php artisan test
composer run test
composer run lint
composer run lint:check
npm run format
npm run format:check
```

## Deploy e Documentacao Relacionada

- O deploy de producao usa `scripts/deploy-ubuntu.sh`.
- O backup e restore do `storage` em producao usa `scripts/docker-volume-backup.sh`.
- O ambiente local usa `docker-compose.local.yml` e `scripts/deploy-local-docker.sh`.
- Documentos adicionais estao em `docs/`.

## Observacoes Operacionais

- O projeto possui comportamento multi-tenant por dominio e subdominio.
- Para rotas por subdominio, o host local precisa resolver `*.akademia.localhost` quando aplicavel.
- Se houver erro de permissao do Docker, adicione o usuario ao grupo `docker` e reabra a sessao.
- Se houver problema de cache Blade ou arquivos compilados, o script local ja limpa os caches de runtime antes de rodar o bootstrap Laravel.
