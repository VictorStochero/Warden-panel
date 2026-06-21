# Warden Panel — Design / Spec

- **Data:** 2026-06-21
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **App:** `warden-panel` (Laravel 13 + Livewire + Flux)

## 1. Resumo

`warden-panel` é uma **aplicação Laravel 13 independente e auto-hospedável** que serve como
**parent dedicado** da frota Warden. É uma **terceira via de instalação**, adicionada **sem
alterar o pacote**, ao lado das duas que o pacote já oferece:

- **Topologia A (parent embutido):** o usuário instala o pacote no próprio app em
  `mode=parent` e usa o mesmo banco. (já existe, não muda)
- **Topologia B (painel dedicado) — viabilizada por este projeto:** o usuário instala o pacote
  no app dele **só como `child`** e roda o `warden-panel` num VPS separado como parent
  exclusivo, recebendo os batches da frota por HTTP.

### Por que existe (objetivo)

1. **Tempo real de verdade.** O dashboard do pacote é Blade puro (zero build step, no máximo
   polling 304) por causa da bandeira *zero runtime dependencies*. O panel pode ter build step
   e Livewire, entregando re-render parcial ao vivo.
2. **Infra própria.** Banco, fila, cache e scheduler do panel rodam num VPS dedicado, **sem
   tocar na aplicação do usuário**.

### Não-objetivos (YAGNI)

- Não reimplementar ingestão nem o pipeline parent (reusa o pacote).
- Não modificar o pacote `victorstochero/warden`.
- Não usar a auth nem as views de dashboard do pacote.
- Sem WebSocket/Reverb no v1 (tempo real via `wire:poll`; push real fica como evolução futura).

## 2. Arquitetura

### 2.1 Postura de dependência

O panel faz `composer require victorstochero/warden:^0.3.5` e **reusa o backend parent
off-the-shelf**, trocando apenas a camada de apresentação:

| Capacidade        | Origem (reuso do pacote)                                              |
|-------------------|----------------------------------------------------------------------|
| Ingestão HTTP     | `routes/warden.php` → `Http/Controllers/IngestController` (token + HMAC-SHA256 + anti-replay + dedup). Mesmo protocolo → **children existentes shippam sem mudar nada**. |
| Pipeline parent   | Comandos `warden:aggregate` / `warden:evaluate` / `warden:partition` / `warden:prune`, **auto-agendados** por `WardenServiceProvider::registerSchedule()` (gate `parent.schedule.enabled`). |
| Leitura           | `Dashboard\DashboardRepository` (~40 métodos: `overview`, `kpis`, `requestSeries`, `topRoutes`, `slowQueries`, `queryHealth`, `recentTraces`, `trace`, `issues`, `incidents`, `recentEvents`, `auditLog`, `distributedTrace`, …). |
| Serviços de gestão| `Issues\IssueWorkflow`, `Projects\ProjectManager`, `Audit\AuditLogger`, `Models\ApiToken` — chamados **direto** (sem passar pelos controllers/rotas/auth do pacote). |
| Schema            | `warden:install` cria as tabelas `wdn_*` no banco do panel.          |

**Configuração que liga isso:**

- `WARDEN_MODE=parent`
- `WARDEN_DASHBOARD=false` — desliga UI/auth/rotas de dashboard do pacote, **mantendo** ingest +
  pipeline (que dependem só de `mode=parent`).
- `WARDEN_CONNECTION=null` — usa a conexão padrão do panel como store `wdn_*` (o app inteiro é
  dedicado, não precisa de conexão `wdn` separada).
- `WARDEN_SELF_MONITOR` opcional (o panel pode se auto-observar como projeto `parent`).

### 2.2 Fluxo de dados

```
child app (mode=child)
   │  POST /warden/ingest  (token + HMAC, protocolo do pacote)
   ▼
warden-panel  ── IngestController (pacote) ──> wdn_events  (banco do panel)
                                                   │
        scheduler do panel ── warden:aggregate ──> wdn_aggregates / wdn_issues / wdn_incidents
                                                   │
        Livewire (wire:poll ~3s) ── DashboardRepository ──> re-render parcial da tela
```

### 2.3 Limite de responsabilidade

- **Reusado, não tocado:** todo o `src/` do pacote (ingest, pipeline, read layer, serviços,
  models, schema).
- **Escrito no panel:** scaffold do starter kit, tema (Warden DS), componentes Livewire das
  telas, telas admin (projetos/tokens), camada de auth/autorização, config/env, scheduler/queue
  do app, docs de deploy.

## 3. Infraestrutura

- **Banco:** escolha do operador via `.env` — MySQL/MariaDB, PostgreSQL ou SQLite. O schema
  `wdn_*` é criado por `warden:install`. **Trade-off documentado:** MySQL/MariaDB habilita
  particionamento RANGE (`warden:partition`/`prune` via `DROP PARTITION`); Postgres/SQLite usam
  o fallback de DELETE em chunks. Migrations do próprio app (users, sessions, jobs, cache)
  convivem no mesmo banco.
- **Fila:** conexão de fila do panel (database por padrão; Redis opcional). Worker do panel.
- **Scheduler:** um único cron (`* * * * * php artisan schedule:run`) dispara o pipeline parent
  auto-registrado pelo pacote. Recomenda-se `aggregate` de minuto em minuto para frescor dos KPIs.
- **Isolamento:** nada disso compartilha processo/banco com a aplicação do usuário.

## 4. Autenticação & autorização

- **Auth = starter kit Livewire** (login, registro, verificação de e-mail, **passkey/WebAuthn**,
  2FA — tudo que o starter kit já entrega). A auth de dashboard do pacote permanece desligada.
- **Registro público desabilitado** por padrão (um painel de frota não pode aceitar cadastro
  aberto na internet). O primeiro operador é criado por seeder/comando de setup; convites de
  novos usuários ficam para iteração futura.
- **Autorização de gestão:** um flag `is_admin` (ou role simples) no `User` separa *viewer* de
  *manager*. Ações de gestão (resolver/ignorar/atribuir issue, criar projeto, emitir token)
  exigem admin; leitura exige apenas usuário autenticado.
- Todas as rotas/telas do panel ficam atrás do middleware de auth do app.

## 5. UI & tempo real

### 5.1 Stack e tema (paridade visual com 0.3.5)

- **Livewire + Flux.** Tema **padronizado com o Warden Design System** do 0.3.5:
  - Cores: accent **Beacon Blue** (`brand` 300–700, `#2E7BFF` base), superfícies dark "night"
    (`ink` 400–950), status `emerald`/`amber`/`rose`/`sky` nos valores do DS.
  - Fontes self-hosted: **Archivo** (UI), **Archivo Expanded** (wordmark WARDEN), **JetBrains
    Mono** (telemetria/métricas). Reaproveitar os `.woff2` do pacote.
  - Sombra `glow` (Beacon) para superfícies primárias/focadas; `darkMode: 'class'`, dark-first.
  - Sidebar replicando os grupos de navegação do pacote: **overview**, **performance**
    (requests/database/jobs/http/schedule), **reliability** (errors/issues/incidents/uptime),
    **diagnostics** (traces/logs/…).
- Os tokens vão para o `tailwind.config.js` do panel; componentes Flux estilizados nesses tokens.
  Onde o layout do Warden diverge dos defaults do Flux, usa-se Blade/Livewire custom sobre o
  mesmo tema.

### 5.2 Tempo real

- Cada componente Livewire de tela usa `wire:poll` (~3s) lendo o `DashboardRepository` e
  re-renderiza **apenas o que mudou** (não a página inteira).
- **Frescor:** listas cruas (`recentEvents`, `recentTraces`, `recentRequests`) ficam
  near-real-time, limitadas só por ingest + intervalo de poll. KPIs agregados acompanham a
  cadência do `warden:aggregate` (rodar de minuto em minuto).
- Intervalo de poll configurável por `.env`.

### 5.3 Inventário de telas (v1 = paridade completa)

1. **Overview de frota** — todos os projetos, saúde, throughput, erros, p95 agregados.
2. **Dashboard por-projeto** — KPIs, séries de request, uptime, e seções
   requests / database (incl. N+1 e query health) / jobs / http / schedule.
3. **Errors** + **Issues** com ciclo de vida (resolver / ignorar / reabrir / atribuir / snooze /
   comentários quando disponíveis).
4. **Incidents.**
5. **Traces** — lista + **waterfall**, incluindo trace distribuído multi-projeto.
6. **Events** + **Logs** (listas recentes por tipo).
7. **Admin** — projetos, **API tokens** (mint), settings, audit log.

## 6. Onboarding de children (admin)

- Telas admin para **projetos** e **API tokens**: criar projeto, emitir token+secret via
  `ProjectManager` / `Models\ApiToken`.
- O **secret do child é exibido uma única vez** na criação (seguir a postura de segurança do
  pacote — secret nunca persiste em claro/sessão cliente). Mostrar o **snippet de setup** do
  child (URL do panel + token + secret) nesse momento.
- É o que fecha a Topologia B ponta-a-ponta: o operador cria o projeto no panel, copia o snippet
  para o `.env` do app-child, e o child passa a shippar.

## 7. Plano de construção (fásico)

Mesmo mirando paridade completa, a entrega é incremental e verificável:

1. **Fundação.** Re-scaffold do starter kit Livewire+Flux sobre o diretório; tema Warden DS;
   `require` do pacote; `mode=parent` + `WARDEN_DASHBOARD=false`; `warden:install`; scheduler +
   queue. **Critério:** um child de teste shippa e os dados aparecem no banco do panel.
2. **Shell do painel.** Layout + sidebar (grupos de nav) + auth do starter kit + autorização
   admin + telas admin de projetos/tokens (com snippet de setup).
3. **Telas de leitura (ondas):** overview + projeto → traces (+waterfall) → issues/incidents →
   events/logs. Todas com `wire:poll`.
4. **Ciclo de vida de issue** + ações de gestão (via `IssueWorkflow`/`AuditLogger`).
5. **Deploy & demo.** README/setup de deploy (subir o panel, apontar children); seed `warden:demo`.

## 8. Testes

- **Feature (PHPUnit/Pest):** ingestão ponta-a-ponta (child fake → ingest → `wdn_events`);
  pipeline (`aggregate` popula `wdn_aggregates`); telas Livewire renderizam e respondem ao poll;
  autorização (viewer vs admin); fluxo de criação de projeto/token e exibição única do secret.
- **Matriz de banco:** rodar a suíte em SQLite (default de CI) e ao menos MySQL para cobrir o
  caminho de particionamento.
- Verificação por etapa antes de declarar uma fase pronta (lint + testes).

## 9. Riscos & verificações

- **Contrato do read layer.** `DashboardRepository`/serviços são API do pacote mas o contrato
  entre versões não é garantido. Mitigação: fixar `^0.3.5`, revisar no upgrade, cobrir com testes
  de feature que quebram cedo se a assinatura mudar.
- **Resolução no container.** Confirmar que `DashboardRepository` e serviços resolvem com deps
  satisfeitas em `mode=parent` com dashboard do pacote desligado. (verificar na Fase 1)
- **Frescor dos KPIs.** Tempo real dos agregados é limitado pela cadência do `aggregate`;
  documentar e default de 1 min.
- **Flux vs Warden DS.** Flux traz visual próprio; paridade exige overrides de tokens e,
  pontualmente, componentes custom.
- **Segurança do secret.** Garantir exibição única e nenhum vazamento em logs/sessão cliente
  (preferir `SESSION_DRIVER` server-side; HTTPS).

## 10. Decisões registradas

| Tema            | Decisão                                                        |
|-----------------|---------------------------------------------------------------|
| Fonte do pacote | Packagist `^0.3.5`                                             |
| Backend         | Reusar parent do pacote (Approach 1), sem reimplementar        |
| Auth            | Starter kit Livewire (passkey); registro público off; flag admin |
| Banco           | Escolha do operador (MySQL/MariaDB/PostgreSQL/SQLite)          |
| Tempo real      | Livewire `wire:poll` (~3s), sem infra extra                    |
| Scaffold        | Re-scaffold com o starter kit oficial                          |
| Escopo v1       | Paridade completa com o dashboard 0.3.5                        |
| Visual          | Padronizado com o Warden Design System do 0.3.5                |
```
