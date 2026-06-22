# Warden Panel — Wave G: Didactic Alerts — Design / Spec

- **Data:** 2026-06-22
- **Status:** Aprovado (pré-implementação)
- **Pacote-alvo:** `victorstochero/warden` `^0.3.5`
- **Objetivo:** mensagens **curtas e acionáveis** ("onde corrigir") por incidente/issue/query lenta —
  em qualquer canal — em vez do alerta técnico terso atual.

## Resumo

O pacote já abre incidentes (issues agrupadas, heartbeats, regras) e envia alertas terso por canais
(`config('warden.alerts.channels')`). Falta a **mensagem didática**: exception + **arquivo:linha do
app** (top frame fora de `vendor/`) + **link** direto para o issue/trace + nome do projeto + contagem.

Wave G entrega:
1. **`App\Alerting\AlertComposer`** — compositor central da mensagem didática (a "semântica", usada por
   todos os canais e pela UI).
2. **"Onde corrigir" na UI** (#4) — bloco destacado na tela de Issue com o top app-frame + link pro trace.
3. **`App\Alerting\PanelDidacticChannel`** (#1/#2) — canal próprio (implementa `Contracts\AlertChannel`,
   registrado em `config/warden.php`) que entrega a mensagem por **webhook (Slack/Discord)** e **e-mail**.
4. **Digest de queries lentas** (#3) — comando agendável que compõe as queries mais lentas como mensagem
   acionável e envia pelos mesmos canais.

Sem modificar `vendor/`. Best-effort (nunca quebra o pipeline). TDD + validação Chrome DevTools + push.

## Parte 1 — AlertComposer (núcleo)

`App\Alerting\AlertComposer` (resolve `DashboardRepository`):

- `forIncident(Incident $incident): array` → `{title, severity, project, where, message, occurrences,
  link, started}`:
  - **issue:** carrega o `Issue` (via `meta.issue_id` ou fingerprint); `class`, `message` (1 linha),
    `count`, `users_affected`, `last_trace_id`.
  - **where (onde corrigir):** do último evento `exception` desse trace (`recentEvents('exception')`
    `firstWhere('trace_id', last_trace_id)`), pega o **primeiro frame de `payload.stack` cujo `file`
    NÃO começa com `vendor/`** → `app/…/Foo.php:42`; fallback `payload.file:payload.line`.
  - **link:** `route('project.issue', [slug, issueId])` (e `route('project.trace', …)` quando houver).
  - heartbeats/regras: versão simples (subject + severity + summary + link do projeto).
- `topAppFrame(array $payload): ?string` — helper público reusado pela UI.

## Parte 2 — "Onde corrigir" na UI (#4)

Na tela de Issue (`App\Livewire\Project\Issue` + view): bloco **"Where to fix"** com o top app-frame
(`app/…:linha`, destacado), o link pro trace (`last_trace_id`) e ocorrências (count/users/first/last).
Tudo leitura via `AlertComposer::topAppFrame` + dados do issue. i18n: `panel.alert.where_to_fix` etc.

## Parte 3 — Canal didático (#1/#2)

`App\Alerting\PanelDidacticChannel implements VictorStochero\Warden\Contracts\AlertChannel`:

- `send(Incident $incident, string $event): void` — compõe via `AlertComposer` e entrega aos transportes
  **configurados**, best-effort:
  - **Webhook (Slack/Discord-compat):** POST JSON (`{text|content: <mensagem markdown>}`) para a URL
    em `Setting::read('panel.alert_webhook')` (vazio → silencia). Detecta Discord vs Slack pelo host
    (`discord` → `content`, senão `text`).
  - **E-mail:** `Mailer::raw(<mensagem>)` para `AlertSetting::current()->recipients` quando
    `email_enabled`. Respeita `min_severity`.
- Mensagem (texto/markdown), ex.:
  ```
  🔴 FooException · Checkout API
  Where: app/Http/Controllers/CheckoutController.php:42
  42 occurrences · 7 users · last 2m ago
  "Undefined array key 'total'"
  → https://panel/projects/checkout-api/issues/123
  ```
- **Registro:** adicionar `App\Alerting\PanelDidacticChannel::class` ao array `alerts.channels` em
  `config/warden.php` (config do app, não do vendor).
- **Config do webhook:** campo "Alert webhook URL" na tela **Settings** (Onda C), persistido via
  `Setting::write('panel.alert_webhook', …)` (chave panel-global; validar `https?://`).
- **Anti-duplicação:** documentar que o canal didático do painel substitui o `MailAlertChannel` terso —
  o operador usa um ou outro (a tela Settings deixa claro). Sem mudança no comportamento do pacote.

## Parte 4 — Digest de queries lentas (#3)

`App\Console\Commands\SlowQueryDigest` (`panel:slow-query-digest`, agendável):

- Para cada projeto ativo, `slowQueries($id, '24h', 5)` (SQL/count/avg/max). Compõe um digest didático
  ("Top slow queries — Checkout API", lista `SQL · avg Xms · N×`), com link para a tela Database.
- Envia pelos mesmos transportes (webhook/e-mail) via um helper compartilhado com o canal.
- Honestidade: query lenta não tem `arquivo:linha` (o recorder não captura o caller); o "onde" é o
  **SQL + a tela Database/o trace** onde aparece. Documentado.

## Testes

- **`AlertComposerTest`:** com um issue + evento exception semeado (stack com frame de app e de vendor),
  `forIncident` retorna o top **app**-frame (não o vendor) e o link correto.
- **`IssueWhereToFixTest`:** a tela de Issue mostra o app-frame e o link pro trace.
- **`PanelDidacticChannelTest`:** com webhook setado, `send()` faz POST (HTTP fake) com a mensagem
  contendo class + app-frame + link; sem URL e sem e-mail, silencia; nunca lança.
- **`SlowQueryDigestTest`:** o comando compõe o digest e dispara o transporte (HTTP/Mail fake).
- **`PanelLayoutRendersTest`** segue verde. Validação Chrome DevTools no bloco "Where to fix".

## DoD

`ddev artisan test` verde; build ok; validado no Chrome DevTools (zero erros de console). Mensagens
didáticas (exception + app-frame + link) disponíveis na UI e entregues por webhook/e-mail; digest de
slow queries agendável. Reuso total do pacote; pacote intocado. Push ao final.
