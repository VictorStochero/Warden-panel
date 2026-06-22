# Warden Panel — Phase 6a: Admin Completeness — Design / Spec

- **Data:** 2026-06-21
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Precede:** Phase 6b (deploy & demo) — planejada em ciclo separado.

## 1. Resumo

Fecha a tela **Admin** do inventário v1 (spec de design §5.3, item 7): além de criar
projeto + emitir credenciais (já feito), o operador passa a **rotacionar tokens**,
**ativar/desativar** projetos e **editar detalhes** — e há uma tela **Audit log** read-only
listando as ações de gestão. Isso completa o ciclo de vida de credencial da **Topologia B**
(criar → distribuir → rotacionar/revogar) e a paridade administrativa com o dashboard 0.3.5.

Tudo via **reuso** do pacote — `ProjectManager` (rotate / setActive / updateDetails) e o
read layer (`DashboardRepository::auditLog`) — sem modificar `vendor/`.

### Não-objetivos (YAGNI)

- **Danger zone destrutiva** (`delete`, `resetMetrics`, `purgeType`): fora do v1; risco alto,
  não exigida para paridade. Fica para ciclo futuro se houver demanda.
- **CRM completo** (`client`/`contact`/`group`/`tags` de `updateDetails`): nesta rodada
  editamos só `name`. Os demais campos ficam para evolução.
- **Deploy/demo** (docker-compose, README, `warden:demo`): Phase 6b.

## 2. Arquitetura

Duas frentes, ambas atrás do gate `panel.manage` (admin), tema Warden DS, tempo real onde
fizer sentido:

1. **`App\Livewire\Admin\Projects` (enriquecer o componente existente).** A lista de projetos
   ganha **ações por linha**:
   - **Rotate token** → `ProjectManager::rotate($project)` → re-exibe o **snippet one-time**
     reusando o mecanismo de flash já existente (`session()->flash('warden_new_credentials', …)`
     + `ProjectManager::envSnippet`). Secret nunca persiste em estado de cliente.
   - **Activate / Deactivate** → `ProjectManager::setActive($project, bool)` (toggle). Desativar
     bloqueia o ingest do child sem deletar — o caminho de "revogar acesso".
   - **Edit name** → `ProjectManager::updateDetails($project, ['name' => …])` via modal Flux.
   - Cada ação **grava uma entrada de auditoria** (ver §4).
2. **`App\Livewire\Admin\Audit` (novo).** Página read-only listando o audit log panel-wide via
   `DashboardRepository::auditLog($limit)`, atualizando via `wire:poll`. Rota `admin.audit`,
   item no grupo de nav "Warden" (ao lado de Projects, gated por `panel.manage`).

### Limite de responsabilidade

- **Leitura** do audit log só via `DashboardRepository::auditLog`. Sem query direta a `wdn_*`.
- **Escrita** de credenciais/detalhes só via `ProjectManager`. **Escrita** de auditoria só via o
  model público `VictorStochero\Warden\Models\AuditLog` (ver Decisão D1).

## 3. Read-layer & write-layer reference (assinaturas exatas — consumir verbatim)

- `ProjectManager::rotate(Project $project): array` → `['token' => string, 'secret' => string]`
  (faz o UPDATE dentro de `Warden::withoutRecording`).
- `ProjectManager::setActive(Project $project, bool $active): void`.
- `ProjectManager::updateDetails(Project $project, array $data): void` — aceita
  `name`/`client`/`contact`/`group`/`tags`; **usamos só `name`** nesta fase.
- `ProjectManager::envSnippet(string $slug, string $token, string $secret, string $url): string`.
- `DashboardRepository::auditLog(int $limit = 200): Collection<int, \stdClass>` de
  `wdn_audit_log` **ordenado por `id` desc**. Colunas:
  `{id, actor, action, target (nullable), method, ip (nullable), meta (array|null), created_at}`.
- `VictorStochero\Warden\Models\AuditLog` — `protected $guarded = []`, `timestamps = false`,
  casts `meta => array`, `created_at => datetime`. Campos graváveis:
  `actor, action, target, method, ip, meta, created_at`.

## 4. Audit: como as entradas são gravadas (Decisão D1)

O middleware `AuditManageActions` do pacote grava a partir das **rotas do dashboard do pacote**,
que este painel **não usa** (rotas próprias; ações via `/livewire/update`). Logo, para o audit log
refletir as ações do painel, **cada ação de gestão grava a própria entrada** via o model público
`AuditLog::create([...])`, dentro de um helper privado no componente:

```php
private function audit(string $action, ?string $target): void
{
    \VictorStochero\Warden\Models\AuditLog::create([
        'actor'      => auth()->user()?->email ?? 'local',
        'action'     => $action,          // ex.: 'panel.project.rotate'
        'target'     => $target,          // ex.: slug do projeto
        'method'     => 'PANEL',          // origem painel (vs HTTP do dashboard do pacote)
        'ip'         => request()->ip(),
        'meta'       => null,
        'created_at' => now(),
    ]);
}
```

- Prefixo `panel.` nas ações para distinguir da origem do dashboard do pacote.
- Best-effort: se a escrita falhar, **não** quebra a ação (try/catch silencioso, como o pacote).
- Sem segredo no audit: nunca gravar token/secret em `meta`.

## 5. Autenticação & autorização

- Ambas as frentes atrás de `@can('panel.manage')` no nav e `$this->authorize('panel.manage')`
  no `mount()` e em **toda** ação de escrita (mesma postura do `createProject` atual).
- Slug/projeto resolvidos por `ProjectManager`/Eloquent; alvo inexistente → 404.

## 6. UI & tempo real

- **Projects:** ações por linha (Flux dropdown/buttons); modal Flux para editar nome; o bloco de
  credenciais one-time (flash) já existente passa a servir create **e** rotate.
- **Audit:** `flux:table` com colunas `Time / Actor / Action / Target / Method / IP`,
  `wire:poll.{{ config('panel.poll_seconds') }}s`, tema Warden DS (`bg-ink-850`, `font-mono`).
- Ícones Flux validados contra `vendor/livewire/flux/stubs/resources/views/flux/icon/<name>.blade.php`.

## 7. Testes

- **`ProjectsAdminTest`** (enriquecer/criar): auth-gating (`panel.manage`); rotate gera novas
  credenciais e re-exibe snippet (sem persistir secret em estado de cliente); setActive alterna
  `active`; updateDetails altera `name`; cada ação grava uma entrada `AuditLog` com `action`
  prefixada `panel.` e `target` = slug.
- **`AdminAuditTest`** (novo): renderiza a página; `assertViewHas('entries')`; auth redireciona
  não-logado; não-admin (sem `panel.manage`) recebe 403.
- **`PanelLayoutRendersTest`**: adicionar `/admin/audit` ao dataset (render full-layout → 200).
- Pest, SQLite `:memory:`, seed via `ddev artisan warden:project`. Sem `warden:demo`.
- **Validação no Chrome DevTools local (DDEV)** ao fim de cada frente: navegar logado, exercitar
  rotate/toggle/edit e a página de audit, conferir **zero erros de console** antes do commit.

## 8. Definition of Done

- `ddev artisan test` verde; `ddev npm run build` ok.
- Admin permite criar, **rotacionar**, **ativar/desativar** e **editar nome** de projetos, cada
  ação auditada; página **Audit log** lista as entradas com `wire:poll`. Ambas gated por
  `panel.manage` e cobertas por `PanelLayoutRendersTest`.
- Todo acesso via `ProjectManager` / `DashboardRepository` / model `AuditLog`; pacote intocado;
  nenhum secret vaza para log/estado de cliente.

## 9. Decisões registradas

| # | Tema | Decisão |
|---|------|---------|
| D1 | Escrita de auditoria | Componente grava via `Models\AuditLog::create()` (middleware do pacote não observa rotas do painel); ações prefixadas `panel.`, best-effort, sem segredo em `meta`. |
| D2 | Escopo de `updateDetails` | Só `name` no v1; `client/contact/group/tags` adiados. |
| D3 | Danger zone | `delete/resetMetrics/purgeType` fora do v1 (risco; não exigido para paridade). |
| D4 | Snippet de rotate | Reusa o flash one-time do create; secret nunca persiste em estado de cliente. |

## 10. Fora de escopo (ciclo seguinte)

- **Phase 6b — Deploy & demo:** docker-compose, README de deploy (subir o painel, apontar
  children), polish do seed `warden:demo`. Fecha a Topologia B ponta-a-ponta operacionalmente.
