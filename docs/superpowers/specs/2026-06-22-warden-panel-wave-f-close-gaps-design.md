# Warden Panel — Wave F: Closing the 4 Remaining Gaps — Design / Spec

- **Data:** 2026-06-22
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Fecha:** as 4 features aparadas após a paridade (Ondas A–E). Ordem: #1 → #4 → #2 → #3.

## Resumo

1. **Range custom exato (#1):** honrar a janela `from→to` real via `DashboardRepository::withWindow()`
   em vez de arredondar para o preset mais próximo (correção de um corte feito na Onda E).
2. **Editar capture/sampling (#4):** no admin por-projeto, escolher capture profile (lean/full/custom)
   e, no custom, ligar/desligar tipos (type-gate) — reusando `CaptureStatus::migrateToLean`,
   `Config\ProjectConfig::sanitize` e `Config\CaptureProfiles`.
3. **API de leitura + tokens (#2):** um endpoint JSON read-only autenticado pela middleware do pacote
   `Http\Middleware\AuthorizeApiToken`, dando consumidor aos API tokens.
4. **i18n do conteúdo (#3):** traduzir o conteúdo de alta visibilidade das telas (KPI labels, títulos
   de seção, cabeçalhos de tabela, empty-states, botões comuns) para PT/ES via `lang/*/panel.php`.

Regras mantidas: sem modificar `vendor/`; leitura via `DashboardRepository`; Pest + validação Chrome
DevTools (DDEV) antes de cada commit; push ao final.

---

## Parte 1 — Range custom exato (#1)

**Mecanismo:** `DashboardRepository::withWindow(?Carbon $start, ?Carbon $end): static` faz
`rangeStart()` devolver `$start` e `rangeEnd()` limitar em `$end`, ignorando o preset.

**Implementação:**
- Trait `App\Support\ResolvesWindow`: props `#[Url] public string $from = '';` `#[Url] public string $to = '';`
  e método `protected function applyWindow(DashboardRepository $dashboard): void` que, quando `from` e
  `to` são parseáveis (`Y-m-d\TH:i`), chama `$dashboard->withWindow(Carbon::parse($from), Carbon::parse($to))`.
  Inválido/ausente → no-op (usa o preset `range`).
- Componentes de seção com range (`Show, Requests, Database, Jobs, Http, Schedule, Logs, Mail, Host,
  Events`): `use ResolvesWindow;` + `$this->applyWindow($dashboard);` no início do `render()`.
- `<x-panel.page-header>`: o picker custom passa a `$set('from', …)`/`$set('to', …)` (não mais mapear
  para preset) e mostra um rótulo "custom" ativo + botão "clear" (`$set('from','')`/`$set('to','')`).
- `App\Support\Window::nearestPreset` deixa de ser usado pelo header (mantido só se referenciado em teste,
  ou removido). 

**Teste:** `CustomRangeExactTest` — setar `from`/`to` válidos faz o componente chamar `withWindow`
(verificar via dados: semear eventos dentro/fora da janela e conferir o KPI/contagem reflete a janela
exata, não o preset). Inválido → cai no preset.

---

## Parte 2 — Editar capture/sampling (#4)

**Superfícies reusadas:** `Dashboard\CaptureStatus::migrateToLean(Project)`,
`Config\CaptureProfiles::{LEAN,FULL,CUSTOM,lean()}`, `Config\ProjectConfig::sanitize(array)`.

**No `App\Livewire\Admin\Project` (página Manage):**
- Card **Capture** com select de profile (`lean`/`full`/`custom`) + (quando custom) toggles por tipo
  de recorder (`query, exception, log, job, mail, notification, cache, command, schedule, http`).
- Ações:
  - **Lean** → `CaptureStatus::migrateToLean($project)`.
  - **Full** → `$project->forceFill(['capture_profile' => CaptureProfiles::FULL, 'config' => null,
    'config_version' => $project->config_version + 1])->save();`
  - **Custom** → montar `['sample' => ['type_gate' => [<tipo> => false, …]]]` para os tipos
    desligados, `ProjectConfig::sanitize($document)`, depois `forceFill(['config' => $sanitized ?: null,
    'config_version' => +1, 'capture_profile' => CaptureProfiles::CUSTOM])->save();`
- Cada ação **auditada** (`panel.project.capture`, meta `['profile' => …]`). Gated `panel.manage`.

**Teste:** `AdminProjectCaptureTest` — setar lean aplica o profile (capture_profile='lean');
custom com um tipo desligado grava `config.sample.type_gate[tipo]=false` e capture_profile='custom';
full limpa. Não-admin → 403.

---

## Parte 3 — API de leitura + tokens (#2)

**Rotas:** `routes/api.php` (registrar via `bootstrap/app.php` `api: __DIR__.'/../routes/api.php'`),
grupo com middleware `VictorStochero\Warden\Http\Middleware\AuthorizeApiToken` (valida bearer token
contra os hashes + `touchLastUsed`). Prefixo `/api/v1`.

**Endpoints (read-only, JSON via `DashboardRepository`):**
- `GET /api/v1/overview` → `overview()` (projects + contadores).
- `GET /api/v1/projects/{slug}` → `project()` + `kpis($id, $range)`.
- `GET /api/v1/projects/{slug}/events/{type}` → `recentEvents($id, $type, $limit, $range)` (type
  allow-listed; range sanitizado).
Controller `App\Http\Controllers\Api\ReadController`. Slug inexistente/type inválido → 404/422.

**Teste:** `ApiReadTest` — sem token → 401; com token mintado válido → 200 e JSON com as chaves
esperadas; token revogado → 401; type fora do allow-list → 422.

> A tela de API Tokens (Onda E) passa a ter consumidor real. Documentar o uso (header
> `Authorization: Bearer wdn_…`) num pequeno bloco na própria tela.

---

## Parte 4 — i18n do conteúdo (#3)

**Escopo (alta visibilidade, via `lang/{en,pt,es}/panel.php`):**
- **KPI strip:** 8 labels (Throughput, Error rate, p95, Slow, Failed jobs, Cache hit, Open issues, Uptime).
- **Section page titles:** o sufixo de cada `<x-panel.page-header :title>` (`· Database` → `· Banco de
  dados` etc.) — usar as chaves `panel.nav.*` já existentes.
- **Cabeçalhos de tabela e empty-states** das views de seção (Route/Count/p95/Errors; Time/Status/
  Duration; "No data"/"No … in this window"; etc.) e labels comuns de botão (Save, Run, Delete, All).
- **Admin:** títulos e labels das telas de admin (Projects/Manage/Maintenance/Settings/Audit/API Tokens).

Adicionar as chaves em `panel.php` (en/pt/es) e trocar as strings hardcoded por `__('panel.…')` nas
views. Conteúdo dinâmico do banco (nomes, mensagens de log/exception) permanece como está (não se traduz).

**Teste:** `ContentLocaleTest` — com locale `pt`, uma view de seção mostra um label de conteúdo em PT
(ex.: KPI "Throughput" → "Throughput"? manter nomes técnicos; usar um label claramente traduzido como
"Erros"/"Disponibilidade" ou um cabeçalho de tabela). Verificar PT e fallback en.

> **Nota de escopo:** cobre o conteúdo de maior visibilidade. Strings muito pontuais podem permanecer em
> inglês e ser traduzidas incrementalmente; nenhum texto dinâmico do banco é traduzido.

## Ordem & DoD

Implementar #1 → #4 → #2 → #3, cada um TDD → build → **devtools** → commit. Ao final: `ddev artisan test`
verde, `ddev npm run build` ok, validação Chrome DevTools sem erros de console, e **push**.

DoD: range custom exato; capture/sampling editável no admin; API de leitura autenticada por token
funcionando; conteúdo de alta visibilidade em EN/PT/ES. Tudo via reuso do pacote; pacote intocado.
