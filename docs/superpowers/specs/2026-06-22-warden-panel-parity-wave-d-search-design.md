# Warden Panel — Dashboard Parity, Wave D: Global Search (⌘K) — Design / Spec

- **Data:** 2026-06-22
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Parte de:** paridade com o parent dashboard (Ondas A→B→C→D). Esta é a **Onda D** (última).

## 1. Resumo

Adiciona a **busca global** (command palette) que o dashboard do pacote tem: um overlay acionado por
**⌘K / Ctrl-K** que busca, read-only, **projetos** (global) e — quando há um projeto no contexto da
rota atual — **rotas, issues e traces** daquele projeto, mostrando resultados agrupados com links
navegáveis. Lê só via `DashboardRepository::search()`. Sem modificar `vendor/`.

## 2. Arquitetura

Um componente Livewire **global** `App\Livewire\Search`, incluído no layout (`<livewire:search />`),
independente da página atual:

- Estado: `public bool $open = false;` `public string $q = '';`
- **Atalho:** Alpine no overlay escuta `keydown.cmd.k`/`keydown.ctrl.k` na window → abre e foca o input;
  `Escape` fecha.
- **Contexto de projeto:** lê o slug da rota atual (`request()->route('slug')`); se resolver via
  `DashboardRepository::project($slug)`, usa o `id` para enriquecer a busca (routes/issues/traces).
- **render:** com `strlen(trim($q)) >= 2`, chama `$dashboard->search($q, $projectId)` →
  `['projects','routes','issues','traces']`; abaixo disso, resultados vazios.
- Resultados agrupados como links `wire:navigate`:
  - project → `route('project.show', $p['slug'])` (label `name`, sub `slug`).
  - route → `route('project.requests', $slug)` (label `route`) — só com contexto de projeto.
  - issue → `route('project.issue', [$slug, $issue['id']])` (label `class`, sub `message`).
  - trace → `route('project.trace', [$slug, $t['trace_id']])` (label `label`, sub `trace_id`).
- Disponível a qualquer usuário autenticado (read-only, sem `panel.manage`).

### Read-layer reference (verbatim)

- `DashboardRepository::search(string $term, ?int $projectId): array` →
  `['projects' => [{name,slug}], 'routes' => [{route}], 'issues' => [{id,class,message}], 'traces' => [{label,trace_id}]]`.
  Routes/issues/traces só vêm preenchidos quando `$projectId !== null`.

## 3. UI

- Trigger visível no header (um botão "Search ⌘K") **e** o atalho de teclado global.
- Overlay centralizado (Flux modal ou um painel `fixed` com backdrop), input no topo
  (`wire:model.live.debounce.300ms="q"`), listas agrupadas com rótulo do grupo; estados vazios
  ("Type to search…", "No matches"). Tema Warden DS.

## 4. Testes

- **`SearchTest`** (Livewire): com um projeto semeado, `set('q','…')` retorna o projeto em
  `assertViewHas('results')` / `assertSee`; `q` curto (<2) não busca; sem contexto de projeto,
  routes/issues/traces ficam vazios; busca é acessível a usuário não-admin (read-only).
- **`PanelLayoutRendersTest`** permanece verde (o `<livewire:search />` no layout não quebra render).
- **Validação no Chrome DevTools local (DDEV):** abrir com ⌘K, digitar, ver resultados e navegar;
  **zero erros de console**, antes do commit.

## 5. Definition of Done

- `ddev artisan test` verde; `ddev npm run build` ok.
- Palette global por ⌘K + botão no header, buscando projetos (global) e rotas/issues/traces no
  contexto de projeto, via `DashboardRepository::search`. Coberto por teste + render test. Validado no
  Chrome DevTools — zero erros de console.

## 6. Conclusão da paridade

Com a Onda D, o painel atinge paridade de organização e features com o parent dashboard do pacote
0.3.5 (navegação em 5 grupos + shell, todas as seções, admin completo, busca global), mantendo as
adaptações do painel (Livewire/Flux, `wire:poll`, auth do starter-kit). Deferidos explicitamente:
project edit "rico" (capture/sampling) e API tokens read-only.
