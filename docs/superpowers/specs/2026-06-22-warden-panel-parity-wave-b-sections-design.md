# Warden Panel — Dashboard Parity, Wave B: Missing Sections — Design / Spec

- **Data:** 2026-06-22
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Parte de:** paridade com o parent dashboard (Ondas A→B→C→D). Esta é a **Onda B**. Depende da A.

## 1. Resumo

Adiciona as telas de projeto que o pacote tem e o painel não: **Requests**, **Errors**, **Mail**,
**Host**, **Security**, **Delivery**, mais **Event detail** e **Incident detail**. Cada uma é um
componente Livewire sob `App\Livewire\Project`, lendo métodos já existentes do `DashboardRepository`,
usando o shell da Onda A (header + KPI-strip + banners), `wire:poll`, tema Warden DS. Sem modificar
`vendor/`.

Com a Onda B, os 5 grupos da sidebar ficam completos — em especial o grupo **System** (Mail/Host/
Security/Delivery), hoje vazio.

### Não-objetivos

- Gráficos SVG: o painel mantém o estilo atual (KPIs + tabelas + sumários textuais). Charts ficam
  como polish futuro.
- Admin (Onda C) e busca ⌘K (Onda D).

## 2. Arquitetura — telas e dados (consumir verbatim)

Cada item: rota, item de nav (grupo), métodos do `DashboardRepository`.

1. **Requests** — `project.requests` (Performance). `kpis($id,$range)`, `requestSeries($id,$range)`
   (sumário), `topRoutes($id,$range,50,false)`, `recentRequests($id,60,$range,false)`. Header com range.
2. **Errors** — `project.errors` (Reliability). `recentErrors($id,50,$release)` (eventos `request`
   com status ≥ 500), `releases($id,20)` para o filtro de release (`#[Url] $release`). Header sem range.
3. **Mail** — `project.mail` (System). `breakdown($id,'mail',$range)`, `breakdown($id,'notification',$range)`,
   `recentEvents($id,'mail',50,$range)`, `recentEvents($id,'notification',50,$range)`. Header com range.
4. **Host** — `project.host` (System). `hostLatest($id,$range)` (meta: `cpu`,`mem`,`load`,`disk`),
   `hostSeries($id,$range)` (`{bucket,cpu,mem}`). KPIs cpu/mem/load/disk + sumário da série. Header com range.
5. **Security** — `project.security` (System). `recentEvents($id,'security',1,$range)->first()` → último
   audit (payload com advisories por severidade). Header sem range.
6. **Delivery** — `project.delivery` (System). `delivery($id,60)` → `{last,window,batches,events,
   cadence,series[],recent[]}` (recent: `{received_at,batches,events}`). Header sem range; LIVE on.
7. **Event detail** — `project.event` (`/projects/{slug}/events/{eventId}`, sem nav). `event($id,$eventId)`
   → `?stdClass {id,trace_id,span_id,parent_span_id,type,occurred_at,duration_us,payload(decoded),release}`;
   null → 404. Mostra metadados + payload (json) + link ao trace.
8. **Incident detail** — `project.incident` (`/projects/{slug}/incidents/{incidentId}`, sem nav).
   `incident($id,$incidentId)` (`?Incident`; null → 404) + `relatedContext($id)` (issues/traces/incidents).
   Mostra cabeçalho (subject/summary/severity/status/started) + contexto.

## 3. Navegação (sidebar, Onda A → completa)

- **Performance:** Database · Jobs · HTTP · Schedule **+ Requests**.
- **Reliability:** Issues · Incidents · Uptime **+ Errors**.
- **System (novo grupo populado):** Host · Mail · Security · Delivery.
- Event/Incident detail: linkados das listas (Events/Incidents), sem item de nav.

## 4. Componentes & isolamento

- Cada seção: um componente Livewire pequeno (`mount` resolve o projeto/404; `render` lê o repo) + uma
  view que inclui `<x-panel.banners>`, `<x-panel.page-header>` e, quando faz sentido (séries/KPIs),
  `<x-panel.kpi-strip>`. Errors/Security/Event/Incident não mostram KPI-strip (telas de detalhe/lista).
- Filtros `#[Url]` allow-listed: `range` (via `Ranges::sanitize`), `release` em Errors validado contra
  `releases()`.
- Detail pages resolvem o id numérico na rota (`whereNumber`) e dão 404 quando o método retorna null.

## 5. Testes

- Um teste de feature por tela (Pest/Livewire): renderiza (`assertViewHas` da chave principal), exige
  auth (`/login` redirect), e — onde há filtro — valida a coerção do `#[Url]` ao allow-list.
- Detail pages: id válido renderiza; id inexistente → 404.
- **`PanelLayoutRendersTest`**: adicionar as 6 seções + (event/incident detail com ids semeados) ao
  dataset.
- Pest, SQLite `:memory:`, seed via `warden:project`. Sem `warden:demo`.
- **Validação no Chrome DevTools local (DDEV):** abrir cada seção nova, conferir nav (grupo System
  populado), header/KPI/filtros, **zero erros de console**, antes do commit.

## 6. Definition of Done

- `ddev artisan test` verde; `ddev npm run build` ok.
- As 6 seções + Event/Incident detail existem, na navegação correta (5 grupos completos), lendo só via
  `DashboardRepository`, com o shell da Onda A. Cobertas por testes + `PanelLayoutRendersTest`.
- Validado no Chrome DevTools local — zero erros de console.

## 7. Próximas ondas

- **C — Admin parity:** Maintenance, Alert Settings, rich Project edit.
- **D — Busca global ⌘K.**
