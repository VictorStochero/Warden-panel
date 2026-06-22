# Warden Panel — Wave E: Visual Fidelity, Theme Correction & Remaining Gaps — Design / Spec

- **Data:** 2026-06-22
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Parte de:** paridade com o parent dashboard. Ondas A–D entregaram a IA e as telas; esta **Onda E**
  fecha a **fidelidade visual** (tema/cores + gráficos + widgets ricos) e as **11 lacunas** restantes.

## 0. Resumo e princípios

As Ondas A–D trouxeram paridade de navegação e de telas. Faltam: (a) **correção de cores** — o painel
ainda usa o preto neutro `zinc` do starter-kit como fundo, com KPIs todos em azul, em vez da paleta
**Warden DS** (superfícies *ink* navy + valores brancos com tom de saúde); (b) **gráficos** (o pacote
usa SVG; o painel usa texto); (c) **widgets ricos** (Overview por-projeto e por-frota); e (d) lacunas
pontuais (filtros de Logs, cache stores, deploy markers, range custom, banner de versão, painel
"Related", API Tokens, i18n).

Regra mantida: **sem modificar `vendor/`**; leitura só via `DashboardRepository`; `wire:poll`;
Pest + validação no **Chrome DevTools local (DDEV)** antes de cada commit; só ícones Flux válidos.

---

## Parte 1 — Correção de tema/cores (base de tudo)

**Diagnóstico (exato):**
- `resources/views/components/layouts/app/sidebar.blade.php`: `body` usa `bg-white dark:bg-zinc-800`
  e a sidebar `dark:bg-zinc-900`/`dark:bg-zinc-700` — **preto neutro do starter-kit**, não o *ink* navy.
- `resources/css/app.css` **já define** a paleta Warden DS: brand "Beacon Blue"
  (`--color-brand-400:#5B97FF`, `-500:#2E7BFF`), *ink* navy (`--color-ink-850:#111726`,
  `-900:#0A0E18`, `-950:#070A12`). Só não está aplicada ao fundo.
- KPI-strip (`resources/views/components/panel/kpi-strip.blade.php`) pinta **todos** os valores em
  `text-brand-400` (azul). No pacote, o KPI default é **branco** (`tone='slate' → text-white`), com
  tom de saúde só onde faz sentido (`emerald/amber/rose`).

**Mudanças:**
1. **Fundo & sidebar** (layout): `body` → `bg-ink-950 text-slate-200`; `flux:sidebar` →
   `bg-ink-900 border-ink-800` (remover `bg-white`/`dark:bg-zinc-*`/`bg-zinc-50`). Header mobile
   (`flux:header`) idem ink. Manter `class="dark"` no `<html>`.
2. **KPI-strip:** valor default **branco** (`text-white`); aceitar um `tone` por card
   (`emerald/amber/rose/brand`) só para métricas de saúde — error_rate (rose se alto), p95
   (amber se alto), uptime (emerald). Manter o primeiro card com `shadow-glow`.
3. **Cards padrão:** manter `bg-ink-850` com `border border-ink-700/60 rounded-xl` (espelha o
   `rounded-2xl border border-ink-700/70 bg-ink-900` do pacote — adotar `bg-ink-900` nos cards de
   KPI e `bg-ink-850` nas tabelas/painéis, para a mesma hierarquia de profundidade).
4. **Acento Flux** já é Beacon Blue (`--color-accent`). Sem mudança.
5. **Regressão visual:** `PanelLayoutRendersTest` continua verde; validação devtools confere o fundo
   navy e KPIs brancos.

**Arquivos:** layout sidebar/header; `kpi-strip.blade.php` (+ helper de tom). **Teste:**
`ThemeColorsTest` — o layout renderiza com `bg-ink-950` e não contém `bg-zinc-800`; KPI-strip default
não usa `text-brand-400` no valor de Throughput.

---

## Parte 2 — Gráficos (lacuna #1)

Dois componentes Blade reutilizáveis espelhando os primitivos do pacote (`partials/bars`, `partials/chart`):

- **`<x-panel.bars :values :color :height />`** — barras div-based: container `bg-ink-850 rounded-lg`,
  cada barra `flex-1 rounded-full` com altura `% do max` e opacidade `0.85/0.18`. Empty-state textual.
- **`<x-panel.chart :values :color :height :fill />`** — line chart SVG (`viewBox 0 0 600 h`,
  `polyline` + `polygon` de área a 12% de opacidade), `vector-effect=non-scaling-stroke`. Empty-state.

Cores: throughput `#5B97FF` (brand), erros `#fb7185` (rose), p95 `#fbbf24` (amber), cpu `#6366f1`,
mem `#10b981`. `values` são listas numéricas extraídas das séries já carregadas.

**Aplicação imediata (substituir sumários textuais por gráficos):**
- **Overview (Show):** card Throughput com `<x-panel.bars>` (counts por bucket de `requestSeries`),
  card p95 com `<x-panel.chart>` (p95 por bucket).
- **Requests:** strip de 3 — Throughput (bars), Errors (bars rose), p95 (chart amber).
- **Host:** charts de CPU e Memory (de `hostSeries`).
- **Logs:** stacked bar de níveis (ver Parte 5).
- **Delivery:** bars da série `delivery['series']`.

**Arquivos:** `components/panel/{bars,chart}.blade.php`; views show/requests/host/delivery.
**Teste:** `ChartComponentsTest` — `<x-panel.bars>`/`<x-panel.chart>` renderizam `<svg`/barras com
dados e o empty-state sem dados (via Blade::render).

---

## Parte 3 — Widgets do Project Overview (lacuna #2)

O Overview por-projeto do pacote tem uma coluna lateral rica. Adicionar ao `Show` (component + view),
layout 2 colunas (conteúdo 2/3 + sidebar 1/3):

- **Component `Show::render`** passa também: `incidents` (`relatedContext` → `incidents`/`recentIssues`)
  ou diretamente: `recentIssues($id,6)`, `incidents($id,6)`, `heartbeats($id)`, `recentTraces($id,12)`,
  `slowQueries($id,$range,6)`, `queues($id,$range)`.
- **Sidebar (1/3):** cards **Active incidents** (se houver; borda rose, link p/ `project.incident`),
  **Recent issues** (até 6, link p/ `project.issue`), **Heartbeats** (status verde/vermelho por tarefa),
  **Recent traces** (até 12, link p/ `project.trace`, badge de erro/duração).
- **Conteúdo (2/3):** Throughput (bars), p95 (chart), **Slowest queries** (tabela 6), **Top routes**
  (já existe), **Queues** (tabela por fila).

**Arquivos:** `Show.php`, `show.blade.php`. **Teste:** `ProjectOverviewWidgetsTest` —
`assertViewHas('recentIssues')`/`'heartbeats'`/`'recentTraces'`/`'slowQueries'`/`'queues'`.

---

## Parte 4 — Fleet Overview rico (lacuna #3)

Trazer o Overview da frota à paridade:

- **Component `Overview`:** `#[Url] $group=''`, `#[Url] $tag=''` (sanitizados contra `groups`/`tags`
  do `overview()`); passar `overview(['group'=>$group,'tag'=>$tag])` → `projects`, `groups`, `tags`,
  contadores. Projects trazem `health`, `error_rate`, `p95_ms`, `throughput`, `last_seen_at`,
  `group?->name`.
- **View:** linha de KPIs (projects/throughput/open issues/open incidents); **pills de filtro** de
  group e tag ("All" + cada um, `$set`); **grid de cards de projeto** **clusterizado por grupo**
  (heading do grupo ou "Ungrouped"), cada card com **health ring** (SVG anel colorido por `health`:
  emerald/amber/rose), nome, slug, throughput, error rate, last seen. Empty-state ("No match").

**Health ring:** pequeno SVG (anel) — cor por `health` (`healthy→emerald`, `warning→amber`,
`down/critical→rose`, senão `slate`).

**Arquivos:** `Overview.php`, `overview.blade.php`, `components/panel/health-ring.blade.php`.
**Teste:** `FleetOverviewTest` — render com cards; `set('group','bogus')` coage para `''`;
`assertViewHas('groups')`/`'tags'`.

---

## Parte 5 — Logs: filtro de nível + busca textual (lacuna #4)

- **Component `Logs`:** `#[Url] $level=''` (allow-list `['','debug','info','warning','error','critical']`),
  `#[Url] $q=''`. Passar `breakdown($id,'log',$range)` (para a contagem por nível) e filtrar
  `recentEvents($id,'log',200,$range)` por `payload['level']===$level` (quando setado) e por
  `str_contains(payload['message'], $q)` (quando `$q!==''`).
- **View:** **stacked bar** de níveis clicável (cada segmento `$set('level', …)`), badge do nível ativo
  + "clear", input de busca `wire:model.live.debounce.300ms="q"`, e a tabela atual de logs.

**Arquivos:** `Logs.php`, `logs.blade.php`. **Teste:** `ProjectLogsFilterTest` — `set('level','error')`
filtra; nível inválido coage para `''`; `set('q','boom')` filtra por mensagem.

---

## Parte 6 — Database: cache stores breakdown (lacuna #5)

- **Component `Database`:** passar `cacheStores($id,$range)` (coleção `{key/store, hits, misses,
  writes, hit_rate}` — consumir os campos retornados).
- **View:** card "Cache stores" com tabela (Store / Hits / Misses / Hit rate / Writes), abaixo de
  Query health / slow / frequent.

**Arquivos:** `Database.php`, `database.blade.php`. **Teste:** `ProjectDatabaseCacheTest` —
`assertViewHas('cacheStores')`.

---

## Parte 7 — Deploy markers + since-deploy (lacuna #6)

- **Requests:** banner de **release markers** (`releaseMarkers($id,$range)` → tags+timestamps) acima
  dos gráficos.
- **Errors:** quando um `release` está filtrado, card **"Since deploy"** com throughput/erros/erro%
  (derivados de `recentErrors`/`kpis` no recorte). Manter o filtro de release existente.

**Arquivos:** `Requests.php`/`requests.blade.php`, `Errors.php`/`errors.blade.php`. **Teste:**
`ProjectDeployMarkersTest` — Requests `assertViewHas('markers')`.

---

## Parte 8 — Range custom `from→to` (lacuna #7)

- **`App\Support\Ranges`:** sem mudança nos presets. Adicionar suporte a janela custom no header.
- **`<x-panel.page-header>`:** além dos pills, um `<details>` com dois `datetime-local` (`from`/`to`)
  e um botão "Apply" que faz `$set('from', …)`/`$set('to', …)` no host.
- **Componentes de seção:** adicionar `#[Url] $from=''`, `#[Url] $to=''`. Quando ambos válidos
  (parse `Y-m-d\TH:i`), derivar a janela; senão, usar `range`. Helper `App\Support\Window::resolve(
  $range,$from,$to): array{label,since,until}` — mas como o repo aceita só `range` string, a janela
  custom é **best-effort**: se setada, escolher o preset mais próximo do span e exibir o rótulo custom
  (sem quebrar contratos do repo). *(Limitação documentada: o read-layer agrega por preset; o custom
  mapeia para o preset mais próximo.)*

**Arquivos:** `Ranges.php`/novo `Window.php`, `page-header.blade.php`, componentes de seção.
**Teste:** `CustomRangeTest` — setar `from`/`to` válidos define o rótulo custom e não quebra o render.

> **Nota de honestidade:** como o `DashboardRepository` agrega por presets, a janela custom no painel
> mapeia para o preset mais próximo (não um intervalo arbitrário). Paridade visual, não de granularidade.

---

## Parte 9 — Banner de nova versão (lacuna #8)

- **`<x-panel.banners>`:** adicionar um banner (brand, dispensável) quando houver release nova.
  Fonte: `VictorStochero\Warden\Models\Setting::read('latest_version')` vs a versão instalada
  (`VictorStochero\Warden\Support\PackageVersion` se exposto; senão `composer.lock`/constante). Render
  **nunca** faz chamada de rede (lê só o `Setting` cacheado). Dispensar grava em sessão
  (`dismissed_version`) — não persiste no pacote.

**Arquivos:** `banners.blade.php` (+ pequeno view-model). **Teste:** `VersionBannerTest` — com
`Setting` de versão futura, o banner aparece; dispensado, some na sessão.

---

## Parte 10 — Painel "Related" de contexto (lacuna #9)

- **`<x-panel.related :project :traceId />`** — painel lateral colapsável (persist. em `localStorage`
  via Alpine) com links de contexto: nas páginas de trace, mostra issues/incidents relacionados
  (`relatedContext($id,$traceId)`); nas demais, recent traces/open issues/incidents
  (`relatedContext($id)`). Incluído opcionalmente nas páginas de detalhe (Trace/Event/Incident).

**Arquivos:** `components/panel/related.blade.php`; views trace/event/incident. **Teste:** coberto pelo
render test (as páginas continuam 200) + devtools.

---

## Parte 11 — Admin API Tokens (lacuna #10)

Implementar a tela de **API Tokens** do pacote (gestão de tokens read-only), via `Models\ApiToken`:

- **`App\Livewire\Admin\ApiTokens`** (`admin.api-tokens`, gated `panel.manage`): listar tokens
  (`name`, `prefix`, `last_used_at`, `created_at`), **criar** (`ApiToken::mint($name)` → exibe o
  plaintext **uma única vez** via flash, como o secret de projeto) e **revogar**
  (`whereKey($id)->delete()`). Cada ação auditada (`panel.token.*`).
- Item de nav "API Tokens" no grupo "Warden".

> Nota: o painel ainda **não serve** uma API de leitura própria; a tela gerencia os tokens (paridade de
> UI e ciclo de vida). Expor o endpoint de leitura fica fora desta onda.

**Arquivos:** `ApiTokens.php`, `admin/api-tokens.blade.php`, rota, nav, render test. **Teste:**
`AdminApiTokensTest` — mint exibe o plaintext uma vez e persiste o hash; revoke remove; auditado;
não-admin → 403.

---

## Parte 12 — i18n: switcher EN/PT/ES (lacuna #11)

Mecanismo de localização + tradução da **navegação e do shell** (escopo honesto; conteúdo por-tela é
incremental):

- **Locale:** middleware/boot lê `session('locale')` (default `en`) e `app()->setLocale`. Allow-list
  `['en','pt','es']`. Um **switcher** no menu de usuário (3 opções) que grava a sessão e recarrega.
- **Strings:** `lang/{en,pt,es}/panel.php` cobrindo os rótulos de nav/shell/grupos e títulos de seção;
  as views passam a usar `__('panel.nav.requests')` etc. **Reuso:** onde o painel mostra strings do
  pacote, usar os arquivos `warden::` já traduzidos (en/pt_BR/es) do vendor.
- Conteúdo de tabelas/tooltip por-tela: tradução incremental (fora do v1 desta onda; documentar).

**Arquivos:** `lang/{en,pt,es}/panel.php`, um `SetLocale` middleware + registro, switcher no
`sidebar.blade.php` (menu), e troca dos rótulos de nav. **Teste:** `LocaleSwitchTest` — setar
`session('locale','pt')` faz o nav renderizar os rótulos PT; locale inválido cai para `en`.

> **Nota de escopo:** i18n completo de todas as ~30 telas é incremental; esta onda entrega o
> **mecanismo + nav/shell traduzidos** (a maior superfície compartilhada), expansível tela a tela.

---

## Ordem de implementação (cada passo: TDD → build → devtools → commit)

1. **Tema/cores** (base — afeta tudo).
2. **Gráficos** (`bars`/`chart`) + aplicação em Show/Requests/Host/Delivery.
3. **Project Overview widgets.**
4. **Fleet Overview rico** (+ health ring).
5. **Logs** filtro+busca (+ stacked bar).
6. **Database cache stores.**
7. **Deploy markers + since-deploy.**
8. **Range custom from→to.**
9. **Banner de versão.**
10. **Painel Related.**
11. **API Tokens.**
12. **i18n switcher + nav.**

## Definition of Done

- `ddev artisan test` verde; `ddev npm run build` ok.
- Fundo navy *ink* (sem `zinc` do starter-kit), KPIs brancos com tom de saúde; gráficos SVG nos lugares
  citados; Overview por-projeto e por-frota ricos (widgets + health rings + filtros); Logs com
  filtro/busca; cache stores; deploy markers; range custom; banner de versão; painel Related; API
  Tokens; switcher de locale com nav traduzida. Tudo via `DashboardRepository`/models do pacote;
  pacote intocado.
- **Validado no Chrome DevTools local — zero erros de console** em cada etapa.
- **Push** ao final.

## Limitações registradas (honestidade)

- Range custom mapeia para o preset mais próximo (o read-layer agrega por preset).
- API Tokens gerencia tokens, mas o painel ainda não expõe a API de leitura.
- i18n entrega mecanismo + nav/shell; conteúdo por-tela é incremental.
- Gráficos são leves (SVG/div), sem tooltips interativos avançados do pacote.
