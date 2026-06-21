# warden-panel — instruções do projeto

## Roteamento de modelo para subagentes (SDD / Agent)

Ao despachar subagentes (implementers, reviewers, fixers) e ao escolher o modelo de tarefas:

- **Tarefas simples / mecânicas** (config, edição de 1–2 arquivos com spec completa,
  transcrição de código já dado no plano, fixes de uma linha): **sonnet**.
- **Demais tarefas** (integração multi-arquivo, julgamento de design, debugging, **todo
  code review / task review**, revisão de branch, decisões de arquitetura): **opus**.

Sempre especificar o modelo explicitamente no dispatch (não herdar o da sessão).
