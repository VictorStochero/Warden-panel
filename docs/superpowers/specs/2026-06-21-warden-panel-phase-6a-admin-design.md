# Warden Panel — Phase 6a: Admin Completeness — Design / Spec

- **Data:** 2026-06-21
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Precede:** Phase 6b (deploy & demo) — planejada em ciclo separado.

## 1. Resumo

Fecha **por completo** a tela **Admin** do inventário v1 (spec de design §5.3, item 7). Além de
criar projeto + emitir credenciais (já feito), o operador passa a, nesta mesma versão:

- **Rotacionar tokens** e **ativar/desativar** projetos (ciclo de vida de credencial / revogar).
- **Editar detalhes completos** do projeto (name, client, contact, group, tags).
- **Manutenção / danger zone:** **reset de métricas**, **purge de um tipo de evento** e
  **deletar o projeto** — com confirmação.
- **Audit log** read-only listando as ações de gestão.

Isso completa o ciclo de vida da **Topologia B** (criar → distribuir → rotacionar/revogar →
descomissionar) e a paridade administrativa com o dashboard 0.3.5. Tudo via **reuso** do pacote —
`ProjectManager` e o read layer (`DashboardRepository::auditLog`) — sem modificar `vendor/`.

### Não-objetivos (YAGNI)

- **Deploy/demo** (docker-compose, README, `warden:demo`): Phase 6b, ciclo separado.
- **Gestão de usuários do painel** (convidar operadores, papéis): fora do v1; o gate `panel.manage`
  é binário por usuário, definido fora desta tela.

## 2. Arquitetura

Para isolar superfícies (lista leve vs settings/destrutivo pesado vs trilha de auditoria), três
componentes Livewire, **todos** atrás do gate `panel.manage`, tema Warden DS:

1. **`App\Livewire\Admin\Projects` (enriquecer o existente)** — a **lista** + create já existentes,
   mais as ações de ciclo de vida rápidas por linha:
   - **Rotate token** → `ProjectManager::rotate($project)` → re-exibe o **snippet one-time**
     reusando o flash existente (`session()->flash('warden_new_credentials', …)` +
     `ProjectManager::envSnippet`). Secret nunca persiste em estado de cliente.
   - **Activate / Deactivate** → `ProjectManager::setActive($project, bool)` (toggle).
   - **Link "Manage"** para a página de admin por-projeto (settings + danger).
2. **`App\Livewire\Admin\Project` (novo, `admin.project` {slug})** — a página de gestão de um
   projeto:
   - **Settings:** form editando `name`, `client`, `contact`, `group`, `tags` →
     `ProjectManager::updateDetails($project, $data)`.
   - **Danger zone** (confirmação obrigatória):
     - **Reset metrics** → `ProjectManager::resetMetrics($project)`.
     - **Purge type** → `ProjectManager::purgeType($project, $type)` (`$type` no allow-list de
       recorders).
     - **Delete project** → `ProjectManager::delete($project)`; **type-to-confirm** (digitar o
       slug) antes de habilitar; redireciona para a lista após deletar.
3. **`App\Livewire\Admin\Audit` (novo, `admin.audit`)** — audit log panel-wide read-only via
   `DashboardRepository::auditLog($limit)`, `wire:poll`.

Cada ação de escrita **grava uma entrada de auditoria** (ver §4). Itens de nav "Projects",
"Audit" no grupo "Warden" (gated `panel.manage`); a página por-projeto é acessada pelo "Manage" da
lista (sem item de nav próprio).

### Limite de responsabilidade

- **Leitura** do audit log só via `DashboardRepository::auditLog`. Sem query direta a `wdn_*`.
- **Escrita** de credenciais/detalhes/manutenção só via `ProjectManager`. **Escrita** de auditoria
  só via o model público `VictorStochero\Warden\Models\AuditLog` (ver Decisão D1).

## 3. Read-layer & write-layer reference (assinaturas exatas — consumir verbatim)

- `ProjectManager::rotate(Project $project): array` → `['token' => string, 'secret' => string]`.
- `ProjectManager::setActive(Project $project, bool $active): void`.
- `ProjectManager::updateDetails(Project $project, array $data): void` — chaves aceitas:
  `name`, `client`, `contact`, `group` (nome; resolvido/criado), `tags` (CSV ou lista; resolvidos/
  criados e sincronizados; vazio limpa associação).
- `ProjectManager::resetMetrics(Project $project): array<string,int>` — apaga linhas de
  `wdn_events/aggregates/issues/incidents/heartbeats/cursors` do projeto; retorna contagem por
  tabela. **Linha do projeto preservada.**
- `ProjectManager::purgeType(Project $project, string $type): array<string,int>` — apaga
  `wdn_events`+`wdn_aggregates` daquele `type`; issues/incidents preservados.
- `ProjectManager::delete(Project $project): array<string,int>` — `resetMetrics` + detach de tags +
  remove a linha do projeto.
- `ProjectManager::envSnippet(string $slug, string $token, string $secret, string $url): string`.
- `DashboardRepository::auditLog(int $limit = 200): Collection<int, \stdClass>` de
  `wdn_audit_log` **ordenado por `id` desc**. Colunas:
  `{id, actor, action, target (nullable), method, ip (nullable), meta (array|null), created_at}`.
- `VictorStochero\Warden\Models\AuditLog` — `$guarded = []`, `timestamps = false`,
  casts `meta => array`, `created_at => datetime`.
- Recorder types (allow-list p/ purge) = `['query','exception','log','job','mail','notification',
  'cache','command','schedule','http','request']` (de `RecorderManager`).

## 4. Audit: como as entradas são gravadas (Decisão D1)

O middleware `AuditManageActions` do pacote grava a partir das **rotas do dashboard do pacote**,
que este painel **não usa** (rotas próprias; ações via `/livewire/update`). Logo, **cada ação de
gestão grava a própria entrada** via o model público `AuditLog::create([...])`, num helper
compartilhado (trait `App\Support\WritesAudit` ou método privado por componente):

```php
protected function audit(string $action, ?string $target, array $meta = []): void
{
    try {
        \VictorStochero\Warden\Models\AuditLog::create([
            'actor'      => auth()->user()?->email ?? 'local',
            'action'     => $action,        // ex.: 'panel.project.rotate', 'panel.project.delete'
            'target'     => $target,        // slug do projeto
            'method'     => 'PANEL',        // origem painel (vs HTTP do dashboard do pacote)
            'ip'         => request()->ip(),
            'meta'       => $meta ?: null,  // ex.: ['type' => 'cache'] no purge; nunca segredos
            'created_at' => now(),
        ]);
    } catch (\Throwable) {
        // Trilha best-effort — nunca quebra a ação (mesma postura do pacote).
    }
}
```

- Ações prefixadas `panel.` para distinguir da origem do dashboard do pacote.
- **Nunca** gravar token/secret em `meta`.

Ações auditadas: `panel.project.create`, `panel.project.rotate`, `panel.project.activate`,
`panel.project.deactivate`, `panel.project.update`, `panel.project.reset`, `panel.project.purge`
(meta `type`), `panel.project.delete`.

## 5. Autenticação & autorização

- Os três componentes atrás de `@can('panel.manage')` no nav e `$this->authorize('panel.manage')`
  no `mount()` e em **toda** ação de escrita (mesma postura do `createProject` atual).
- Projeto resolvido por slug via Eloquent/`ProjectManager`; slug inexistente → 404.
- **Delete** exige confirmação type-to-confirm (digitar o slug) — botão desabilitado até bater.

## 6. UI & tempo real

- **Projects (lista):** create (existente) + por linha: badge ativo/inativo, botões Rotate,
  Activate/Deactivate e link Manage. Bloco de credenciais one-time (flash) serve create **e** rotate.
- **Project (admin por-projeto):** card **Settings** (form name/client/contact/group/tags) + card
  **Danger zone** com Reset metrics, Purge type (select de tipo) e Delete (type-to-confirm), cada
  destrutivo atrás de `flux:modal` de confirmação. Após delete → redireciona para `admin.projects`
  com flash de sucesso.
- **Audit:** `flux:table` com `Time / Actor / Action / Target / Method / IP`,
  `wire:poll.{{ config('panel.poll_seconds') }}s`, tema Warden DS (`bg-ink-850`, `font-mono`).
- Ícones Flux validados contra `vendor/livewire/flux/stubs/resources/views/flux/icon/<name>.blade.php`.

## 7. Testes

- **`ProjectsAdminTest`** (lista): auth-gating; rotate gera novas credenciais e re-exibe snippet
  (sem persistir secret em estado de cliente); setActive alterna `active`; cada ação grava
  `AuditLog` (`action` `panel.*`, `target` = slug).
- **`AdminProjectTest`** (novo, por-projeto): updateDetails altera name/client/contact/group/tags;
  resetMetrics zera as métricas (events do projeto somem) preservando a linha do projeto;
  purgeType remove só o tipo escolhido; delete remove o projeto e **redireciona** para a lista;
  delete sem o slug digitado é no-op; cada ação auditada; não-admin → 403.
- **`AdminAuditTest`** (novo): renderiza; `assertViewHas('entries')`; reflete uma entrada recém-
  gravada; não-logado redireciona; não-admin → 403.
- **`PanelLayoutRendersTest`**: adicionar `/admin/projects/{slug}` e `/admin/audit` ao dataset
  (render full-layout → 200).
- Pest, SQLite `:memory:`, seed via `ddev artisan warden:project`. Sem `warden:demo`.
- **Validação no Chrome DevTools local (DDEV)** ao fim de cada componente: navegar logado,
  exercitar rotate/toggle/edit, danger zone (reset/purge/delete) e o audit, conferir **zero erros
  de console** antes do commit.

## 8. Definition of Done

- `ddev artisan test` verde; `ddev npm run build` ok.
- Admin permite criar, **rotacionar**, **ativar/desativar**, **editar detalhes completos**,
  **resetar métricas**, **purgar um tipo** e **deletar** projetos — cada ação auditada e os
  destrutivos com confirmação; página **Audit log** lista as entradas com `wire:poll`. Tudo gated
  por `panel.manage`; páginas novas cobertas por `PanelLayoutRendersTest`.
- Todo acesso via `ProjectManager` / `DashboardRepository` / model `AuditLog`; pacote intocado;
  nenhum secret vaza para log/estado de cliente.

## 9. Decisões registradas

| # | Tema | Decisão |
|---|------|---------|
| D1 | Escrita de auditoria | Componentes gravam via `Models\AuditLog::create()` (middleware do pacote não observa rotas do painel); ações prefixadas `panel.`, best-effort, sem segredo em `meta`. |
| D2 | `updateDetails` | Editar **todos** os campos suportados (name/client/contact/group/tags) nesta versão. |
| D3 | Danger zone | **Incluída** no v1 (reset/purge/delete) com confirmação; delete exige type-to-confirm do slug. |
| D4 | Snippet de rotate | Reusa o flash one-time do create; secret nunca persiste em estado de cliente. |
| D5 | Layout admin | Três componentes isolados: lista (ciclo de vida rápido), página por-projeto (settings + danger), audit. A página por-projeto não tem item de nav — acessada via "Manage" da lista. |

## 10. Fora de escopo (ciclo seguinte)

- **Phase 6b — Deploy & demo:** docker-compose, README de deploy (subir o painel, apontar
  children), polish do seed `warden:demo`. Fecha a Topologia B ponta-a-ponta operacionalmente.
