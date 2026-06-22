# Warden Panel — Dashboard Parity, Wave A: Navigation & Shell — Design / Spec

- **Data:** 2026-06-22
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Parte de:** paridade com o parent dashboard do pacote (Ondas A→B→C→D). Esta é a **Onda A**.

## 1. Resumo

O parent dashboard do pacote organiza as telas de projeto em **5 grupos de navegação**, dá a cada
seção um **header consistente** (título + seletor de range + indicador LIVE) e um **KPI-strip** de
8 indicadores que linkam para as seções, além de **banners** de estado (nova versão / capture
reduzido / read-only). O painel hoje tem uma sidebar plana, range por-página e KPIs só na Overview.

A Onda A traz o painel à paridade **de organização e shell** — sem ainda adicionar seções novas
(isso é a Onda B). Reorganiza a sidebar Flux nativa nos 5 grupos, move o range para um **header
compartilhado**, padroniza o **KPI-strip** no topo de toda página de projeto, adiciona o
**indicador LIVE** e os **banners**.

### Não-objetivos (Onda A)

- **Seções novas** (Requests/Errors/Mail/Host/Security/Delivery, Event/Incident detail): Onda B.
- **Admin novo** (Maintenance/Settings, Project edit rico): Onda C.
- **Busca global ⌘K:** Onda D.
- **Janela custom `from→to`** no range: fora do v1; mantemos os presets do `Ranges` allow-list
  (`15m/1h/6h/24h/7d/30d`). O painel real-time já é `wire:poll`, então não há transport SSE/304.

## 2. Arquitetura

Três peças, sem modificar `vendor/`:

1. **Sidebar agrupada** (`resources/views/components/layouts/app/sidebar.blade.php`). O grupo plano
   "Project" vira **5 grupos** espelhando o pacote, contendo apenas as telas que já existem (grupos
   ficam ocultos quando vazios; a Onda B os preenche):

   | Grupo | Itens (hoje) |
   |---|---|
   | Overview | Overview |
   | Performance | Database · Jobs · HTTP · Schedule |
   | Reliability | Issues · Incidents · Uptime |
   | Diagnostics | Traces · Logs · Events |
   | System | *(vazio até a Onda B)* |

   O grupo admin "Warden" (Fleet/Projects/Audit) permanece no topo, gated por `panel.manage`.

2. **Header compartilhado** — um componente Blade `resources/views/components/panel/page-header.blade.php`
   usado no topo de cada view de projeto:
   - **Título** (`$title`, ex.: "Checkout API · Database") + subtítulo opcional.
   - **Range control**: pills dos presets (`Ranges::all()`) que disparam `wire:click="$set('range', …)"`
     no componente Livewire host (cada seção mantém `#[Url] public string $range`). Renderiza só
     quando `$showRanges` é true (default true; false em telas sem série temporal).
   - **Indicador LIVE**: ponto pulsante + label, visível quando a página usa `wire:poll`
     (`$live`, default true). Estático/visual — o refresh real é o `wire:poll` existente.

3. **KPI-strip compartilhado** — um componente Blade `resources/views/components/panel/kpi-strip.blade.php`
   exibindo até 8 KPIs (throughput, error rate, p95, slow requests, failed jobs, cache hit, open
   issues, uptime 30d), cada um linkando para a seção relevante. Alimentado por
   `DashboardRepository::kpis($projectId, $range)` (já em uso na Overview). Aparece no topo de toda
   página de projeto (abaixo do header).

4. **Banners** — componentes Blade incluídos no layout/header quando aplicável:
   - **Read-only** (âmbar): mostrado a usuário logado **sem** `panel.manage`.
   - **Nova versão** (brand, dispensável): quando `Setting` do pacote indica release nova
     (`DashboardRepository`/`Models\Setting`); só leitura, link para o Packagist.
   - **Capture reduzido** (por-projeto): quando `project.capture_profile` é `lean`/`custom`
     (reusa `Dashboard\CaptureStatus` do pacote).

## 3. Read-layer reference (consumir verbatim)

- `DashboardRepository::kpis(int $projectId, string $range): object` — fornece throughput, error
  rate, p95, slow requests, failed jobs, cache hit rate, open issues, uptime (já consumido por `Show`).
- `Ranges::all()` / `Ranges::sanitize()` — presets allow-listed (`App\Support`).
- `project.capture_profile` (coluna) + `VictorStochero\Warden\Dashboard\CaptureStatus` — estado de
  captura para o banner.
- `VictorStochero\Warden\Models\Setting` — chave de versão para o banner de nova versão
  (best-effort; o render nunca faz chamada de rede).

## 4. Componentes & isolamento

- `<x-panel.page-header :title :show-ranges :live :range :ranges />` — só apresentação; recebe o
  range atual e emite pills `$set`. Não conhece dados de domínio.
- `<x-panel.kpi-strip :project :kpis />` — só apresentação; mapeia o objeto `kpis` para 8 cards com
  rota-alvo. Reusável em todas as seções.
- `<x-panel.banners :project />` — decide quais banners mostrar a partir do gate e do estado do
  pacote.
- Cada componente de seção Livewire passa a **incluir** `<x-panel.page-header>` + `<x-panel.kpi-strip>`
  no topo da sua view e remover o `<flux:select range>` local. Mantêm `#[Url] range` e
  `updatedRange()`/sanitize.

## 5. UI & tempo real

- Range agora vive no header; mudar o range chama `$set('range', …)` (atualização Livewire viva,
  sem full navigation) e re-renderiza a seção. `#[Url]` mantém o range na URL para deep-link.
- LIVE é puramente visual; `wire:poll.{{ config('panel.poll_seconds') }}s` continua sendo o motor.
- Tema Warden DS (`bg-ink-850`, `text-brand-400`, `font-mono`), ícones Flux validados.

## 6. Testes

- **`SidebarGroupsTest`**: a página de projeto renderiza os headings dos 5 grupos presentes
  (Overview/Performance/Reliability/Diagnostics) e os itens nos grupos certos; admins veem o grupo
  Warden, não-admins não veem Projects/Audit.
- **`PageHeaderTest`** / **`KpiStripTest`** (Livewire/Blade render): o header mostra os pills de
  range e o título; clicar num pill muda `range` no componente host; o KPI-strip mostra os 8 KPIs
  com os hrefs de seção corretos.
- **`BannersTest`**: read-only aparece para não-admin e não para admin; banner de capture aparece
  quando `capture_profile` = lean.
- **`PanelLayoutRendersTest`**: continua verde (todas as páginas → 200) — garante que mover o range
  e injetar header/KPI-strip não quebra nenhum render.
- Pest, SQLite `:memory:`, seed via `warden:project`. Sem `warden:demo`.
- **Validação no Chrome DevTools local (DDEV):** navegar logado, conferir os 5 grupos na sidebar, o
  range no header trocando a janela ao vivo, o KPI-strip e os banners, **zero erros de console**,
  antes do commit.

## 7. Definition of Done

- `ddev artisan test` verde; `ddev npm run build` ok.
- Sidebar de projeto agrupada nos 5 grupos do pacote; range no header (pills + LIVE); KPI-strip de
  8 indicadores no topo de toda página de projeto; banners read-only/nova-versão/capture. Selects de
  range por-página removidos. Tudo coberto por testes + `PanelLayoutRendersTest`.
- Acesso só via `DashboardRepository`/helpers; pacote intocado.
- Validado no Chrome DevTools local — zero erros de console.

## 8. Próximas ondas

- **B — Seções faltantes:** Requests, Errors, Mail, Host, Security, Delivery + Event/Incident detail.
- **C — Admin parity:** Maintenance, Settings de alertas, Project edit rico.
- **D — Busca global ⌘K.**
