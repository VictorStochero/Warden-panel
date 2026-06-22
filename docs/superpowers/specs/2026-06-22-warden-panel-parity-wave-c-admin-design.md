# Warden Panel — Dashboard Parity, Wave C: Admin Parity — Design / Spec

- **Data:** 2026-06-22
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Parte de:** paridade com o parent dashboard (Ondas A→B→C→D). Esta é a **Onda C**.

## 1. Resumo

Adiciona as telas administrativas do pacote que o painel ainda não tem: **Maintenance** (rodar os
comandos de pipeline e ver o output) e **Alert Settings** (e-mail de alertas + regras). Ambas via
reuso direto do pacote (`RunMaintenanceJob::ALLOWED`/`Artisan::call`, models `AlertSetting`/
`AlertRule`), gated por `panel.manage`, no grupo de nav "Warden". Cada ação é auditada (trait
`WritesAudit` da Phase 6a).

### Não-objetivos / deferidos

- **Project edit "rico"** (capture profile / sampling knobs): a edição correta desses campos exige
  replicar a montagem do *documento de configuração de captura* do pacote (`ProjectAdminController`
  monta `sample`/`type_gate` e deriva `capture_profile`). Fazer isso fora do pacote é arriscado e de
  baixo valor para o painel; o painel já edita name/client/contact/group/tags (Phase 6a) e mostra o
  banner de capture (Onda A). **Deferido** (revisitar só se necessário).
- **API Tokens** (read-only API): deferido — o painel não expõe API de leitura (decisão anterior).
- Busca ⌘K: Onda D.

## 2. Arquitetura

Dois componentes Livewire sob `App\Livewire\Admin`, gated `panel.manage`, tema Warden DS:

1. **`App\Livewire\Admin\Maintenance`** (`admin.maintenance`). Lista os comandos permitidos
   (`RunMaintenanceJob::ALLOWED` = `aggregate`,`evaluate`,`prune`,`partition`) com suas
   `DESCRIPTIONS`. Botão "Run" por comando → valida contra a allow-list e roda
   `Artisan::call('warden:'.$cmd)` **síncrono**, captura `Artisan::output()`, faz flash do output e
   **audita** `panel.maintenance.run` (meta `['command' => $cmd]`). `prune` é destrutivo → confirmação.
2. **`App\Livewire\Admin\Settings`** (`admin.settings`). Edita `AlertSetting::current()`
   (`email_enabled` bool, `recipients` lista de e-mails, `min_severity` ∈ `['info','warning','critical']`,
   `cooldown` segundos ≥ 0) e gerencia `AlertRule` (criar/excluir). Regras validadas contra as
   whitelists do pacote: metric ∈ `['error_rate','p95','throughput','errors','slow','failed_jobs',
   'cache_hit_rate']`, op ∈ `['>','>=','<','<=','anomaly']`, window ∈ `['15m','1h','6h','24h','7d']`,
   severity ∈ `['info','warning','critical']`. Salvar audita `panel.settings.update`; criar/excluir
   regra audita `panel.settings.rule.*`.

Nav: itens **Maintenance** e **Settings** no grupo "Warden" (após Audit), gated `panel.manage`.

### Limite de responsabilidade

- Maintenance roda só comandos da allow-list do pacote, via `Artisan::call`. Sem shell arbitrário.
- Settings escreve só via os models do pacote (`AlertSetting`, `AlertRule`); sem query crua.
- Toda escrita audita via `WritesAudit` (Phase 6a), prefixo `panel.`.

## 3. Read/write-layer reference (verbatim)

- `VictorStochero\Warden\Maintenance\RunMaintenanceJob::ALLOWED` = `['aggregate','evaluate','prune','partition']`;
  `::DESCRIPTIONS` (mapa cmd→texto).
- `VictorStochero\Warden\Models\AlertSetting::current(): self` — `email_enabled` bool, `recipients`
  array, `min_severity` string, `cooldown` int; persistir com `->save()`.
- `VictorStochero\Warden\Models\AlertRule` — `$guarded = []`; campos `name,metric,op,threshold,window,
  severity,enabled`; `updateOrCreate(['name'=>…], [...])` e `whereKey($id)->delete()`.
- `Illuminate\Support\Facades\Artisan::call('warden:'.$cmd)` + `Artisan::output()`.

## 4. Autenticação & autorização

- Ambos gated `@can('panel.manage')` no nav e `$this->authorize('panel.manage')` no `mount()` e em
  toda ação de escrita. `prune` exige confirmação (`wire:confirm`).
- Validação server-side contra as allow-lists antes de qualquer `Artisan::call`/persistência.

## 5. Testes

- **`AdminMaintenanceTest`**: renderiza com os 4 comandos; rodar um comando válido audita
  `panel.maintenance.run` (meta command) e faz flash do output; comando fora da allow-list é no-op;
  não-admin → 403.
- **`AdminSettingsTest`**: renderiza com `settings`/`rules`; salvar persiste recipients/min_severity/
  cooldown e audita; min_severity inválido é coagido; criar regra com metric/op/window válidos
  persiste e audita; regra inválida é rejeitada; excluir regra audita; não-admin → 403.
- **`PanelLayoutRendersTest`**: adicionar `/admin/maintenance` e `/admin/settings`.
- Pest, SQLite `:memory:`. **Validação no Chrome DevTools local (DDEV):** rodar um comando de
  maintenance (ex.: aggregate) e ver o output; salvar settings; criar/excluir uma regra; **zero
  erros de console**, antes do commit.

## 6. Definition of Done

- `ddev artisan test` verde; `ddev npm run build` ok.
- Admin com **Maintenance** (rodar aggregate/evaluate/prune/partition com output, prune confirmado) e
  **Alert Settings** (e-mail + regras), cada ação auditada, gated `panel.manage`, na nav "Warden",
  cobertas por testes + `PanelLayoutRendersTest`. Reuso total do pacote; pacote intocado.
- Validado no Chrome DevTools local — zero erros de console.

## 7. Próxima onda

- **D — Busca global ⌘K** (command palette read-only sobre projetos/rotas/issues/traces).
