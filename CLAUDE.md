# Horários Acadêmicos (antigo SGA)

Aplicação web para o IFTO que gera grades horárias automaticamente. PHP 8.3+ com MVC próprio
(sem Composer/frameworks), **SQLite** (arquivo `database/sga.sqlite`; horas/JSON em `TEXT`),
Bootstrap 5.3 servido localmente (`assets/vendor/`, sem CDN). Horários em tempo real
(`hora_inicio`/`hora_fim` como texto "HH:MM:SS"), sem períodos fixos. Também roda como app
desktop Windows (Electron + PHP embutido + SQLite; ver `desktop/`).

## ⚠️ Regras críticas

- **O banco contém DADOS REAIS** (professores, disciplinas e atribuições do campus).
  NUNCA inserir dados fictícios nem remover registros sem confirmação explícita do usuário.
  Para testar o gerador sem gravar: replicar `gerar()` via Reflection pulando `salvar()` (só SELECTs).
- **mbstring NÃO está instalado**: proibido `mb_*`. Para UTF-8 usar PCRE com flag `/u`
  (ex.: `preg_match('/^.{0,50}/us', ...)` para truncar sem quebrar acentos).
- Toda mudança de schema: escrever uma **migration** incremental em
  `database/migrations/NNN_*.sql` (ALTER/CREATE IF NOT EXISTS, não destrutiva) **e**
  refletir no `database/schema.sql` (usado só para bancos novos). Aplicar com
  `php database/migrate.php` (up). Ver "Migrations" abaixo.

## Estrutura

- `app/Core/` — Router (regex), Database (PDO singleton, prepared statements sempre), View
- `app/Controllers/` — BaseController (`get/post/flash/render`) + controllers; rotas em `routes.php`
- `app/Models/` — BaseModel + Curso, Turma, Disciplina, Professor, Sala, Semestre, Horario
- `app/Services/` — ScheduleGenerator, TimeHelper (min↔TIME), Exporter, FeasibilityChecker
- `config/database.php` — credenciais (env vars com fallback)
- Servidor dev: `php -S 127.0.0.1:8080 -t public`

## Fluxo principal

Curso (turno, intervalos, duração de aula) → Turmas → Disciplinas (`qtd_encontros_semanais`,
`qtd_aulas` por encontro, `qtd_professores`, `semestre_oferta` bitmask) → Professores
(disponibilidade, 2 cores únicas da paleta de 50) → Semestre → Atribuição
(`semestre_atribuicoes` com `slot` para múltiplos professores) → Gerar → Grade.

## ScheduleGenerator (app/Services/ScheduleGenerator.php)

Fases: carregar → criar atividades (1 por encontro; encontros divididos entre slots de
professores) → ordenação MCF (menos dias, maior duração, professor mais carregado) → greedy →
busca local (swap de falhas) → `otimizar()` (hill climbing) → `garantirDoisDias()` → salvar.

- **Slots**: `slotsAula()` gera a MESMA malha da grade visual (passos de `duracao_aula_minutos`
  pulando intervalos). Encontros podem ATRAVESSAR intervalos (ex.: 6 aulas = 07:00–11:50 com
  break embutido; `hora_fim` inclui o intervalo). A view `grade.php` divide blocos que cruzam
  intervalo em grupos com rowspan.
- **Falhas** são gravadas no limbo (ver abaixo) e listadas no log da geração.

### Pesos soft (defaults no código; `configuracoes_soft` alimenta o formulário; POST sobrescreve)

Filosofia definida pelo usuário (jun/2026):
- Janelas DENTRO do dia do professor são IRRELEVANTES (`janela_professor=0`,
  `balancear_professor=0`). Janelas das turmas importam (`janela_turma=8`).
- O que importa: **mínimo E alvo de 2 dias de aula por professor, adjacentes**.
  `compactar_professor=2500` (3º dia em diante custa peso×distância; empilhar tudo em 1 dia
  custa peso×0.4), `preferencia_dia2=2000` (2º dia longe do 1º custa peso×distância).
  `garantirDoisDias()` força o mínimo para quem tem 2+ aulas.
- Calibração SEMPRE por simulação a seco com os dados reais antes de mudar defaults.

## Convenções não-óbvias

- Trocar professor na atribuição sincroniza a grade gerada (`salvarAtribuicoes`): troca
  1-para-1 no mesmo slot usa `sincronizarProfessorHorarios` (CASE antigo→novo); professor
  adicionado/removido (estrutura de slots mudou) usa `redistribuirEncontros` (mesma regra
  do gerador: teto nos primeiros slots, ordem dia/hora). Conflitos resultantes vão ao limbo.
- **Limbo**: `horarios.dia_semana = 0` = disciplina sem horário (zona de limbo por turma na
  grade, drag & drop). Excluído de exportações (`semLimbo()`) e da API stats; presente em
  `porGeracao()` (a grade precisa).
- Cada geração SUBSTITUI a anterior (`DELETE FROM geracoes WHERE semestre_id`) — decisão do
  usuário: Regerar = reset intencional. Não exibir "Geração #id" na UI (sem significado).
- Grade (`app/Views/horarios/grade.php`): drag & drop com swap (soltar sobre célula ocupada
  troca — `POST /horarios/geracao/trocar`), Desfazer via sessionStorage (`sgaUndo`; mover
  retorna `anterior`), filtros curso/turma/professor/sala (filtro ativo = somente leitura,
  sem JS de drag), relatório vivo da grade (painel persiste aberto via localStorage),
  impressão por turma (1/página, `@media print`; modal `#modalImprimir` escolhe as turmas
  por checkbox — JS marca `.print-skip` nas não selecionadas e `.print-first` na 1ª visível,
  senão a quebra de página herdada gera folha em branco) e "todos por professor"
  (`/horarios/geracao/{id}/imprimir/professores`), verificador de conflitos para aluno
  multi-período (`POST .../conflitos`, nomes 1/linha).
- `FeasibilityChecker::verificar(semestreId)` roda em `/horarios` e na tela de atribuição:
  encontro maior que os slots do dia, demanda semanal > capacidade da turma, professor sem
  disponibilidade.
- Cores: blocos com fundo = cor primária do professor + sufixo alpha `59` (~35%), texto preto,
  faixa inferior na cor secundária com o nome do professor em branco.
- Backup: item de menu `/backup` (`BackupController`). Exportar = `GET /backup/exportar`
  (`VACUUM INTO` → `.sqlite` em `backups/` + download). Importar = `POST /backup/importar`
  (upload `.sqlite`, valida `integrity_check`, salva `pre_import_*` e substitui o arquivo do
  banco, removendo `-wal`/`-shm` antigos). Tudo via PHP/pdo_sqlite, sem binários externos.
- Copiar semestre: dropdown em `/horarios` (aparece com 2+ semestres) — leva atribuições
  **e a grade** (`geracoes` + `horarios`, contadores recalculados), ambas filtradas pela
  oferta do destino (`d.semestre_oferta & destino.semestre`). A grade do destino é sempre
  substituída (mesmo se a origem não tiver grade), para não sobrar horário apontando para
  atribuição trocada. Redireciona para a grade copiada.

## Decisões do usuário (não sugerir de novo)

- Sem capacidade de sala. Sábado não é letivo (grade renderiza só
  seg–sex). NDAs mantidos (ajudam a visualizar professores). Disponibilidade 07:00–23:00 de
  todos os professores é intencional. Sem histórico de gerações.

## Autenticação (jun/2026)

Login por sessão com **perfil único** (acesso total; sem papéis/permissões). Tabela `usuarios`
(`senha_hash` = `password_hash`/bcrypt). **Sem usuário padrão**: o `schema.sql` não semeia admin.
`App\Core\Auth` (`check/user/id/login/logout`); a guarda em `index.php` (antes do `dispatch`),
quando não há sessão, checa `COUNT(*) usuarios` — se **0**, redireciona para `/setup` (cadastro
do 1º usuário, `AuthController@setup`, loga em seguida); senão para `/login`. Telas `/setup` e
`/login` são standalone (`auth/setup.php`, `auth/login.php` via `View::renderPartial`). CRUD em
`/usuarios` (não pode excluir/desativar a
si mesmo nem deixar o sistema sem usuários). Logout em `/logout`.

## Banco SQLite (jul/2026)

Migrado de MySQL para **SQLite** (decisão do usuário: app monousuário, em dev). `App\Core\Database`
seleciona o driver por `config/database.php` (`$cfg['driver']`): `sqlite` (padrão) ou `mysql`
(mantido via env `DB_DRIVER=mysql` para acessar o banco legado). SQLite habilita
`PRAGMA foreign_keys=ON` e `journal_mode=WAL` por conexão. Arquivo em `database/sga.sqlite`
(gitignored; bloqueado na web por `database/.htaccess` e no desktop pelo `router.php`).
No localhost o arquivo é de `www-data` (Apache mod_php grava). Requer a extensão `pdo_sqlite`.
Schema em dialeto SQLite (`schema.sql`); versão MySQL histórica em `schema.mysql.sql`.
Migração de dados única: `database/migrate_mysql_to_sqlite.php` (só lê o MySQL).
**Dialeto**: NUNCA usar sintaxe MySQL-only — `UPDATE/DELETE ... JOIN` (usar subconsulta no
WHERE), `INSERT IGNORE` (usar `INSERT OR IGNORE`), `TIME_TO_SEC/TIMEDIFF` (aritmética
`substr(hora,1,2)*60+substr(hora,4,2)`), `NOW()` (usar `?` + `date('Y-m-d H:i:s')` do PHP —
`CURRENT_TIMESTAMP` do SQLite é UTC, 3h à frente). Comparação de texto é case/acento-SENSÍVEL
(MySQL não era); para matching de nomes usar `LOWER()` dos dois lados.
**Horas**: colunas de hora são TEXT e comparam lexicograficamente — TODO valor gravado em
`horarios.hora_inicio/hora_fim` deve ser "HH:MM:SS" via `TimeHelper::toHms()` (o MySQL
normalizava "HH:MM"→"HH:MM:SS" na coluna TIME; o SQLite não, e formato misto gera falso
conflito no drag & drop: `'10:20:00' > '10:20'` é true como string).

## Migrations (jul/2026)

`database/migrate.php` (runner sem framework) + `database/migrations/NNN_*.sql` (incrementais)
+ tabela `schema_migrations`. `schema.sql` = schema completo para bancos **novos**; migrations
= deltas para atualizar bancos **existentes** sem perder dados.
- `php database/migrate.php` (up) aplica pendentes; `status` lista; `baseline` marca as atuais
  como aplicadas **sem** rodar (para banco que já está no estado do `schema.sql`).
- Desktop: `main.js` roda `baseline` ao criar o `.sqlite` novo (`ensureDatabase`) e `up` em
  toda abertura → "instalar por cima" atualiza o schema automaticamente.
- **Servidor de produção (fazer 1×):** `php database/migrate.php baseline` para colocá-lo sob
  controle; depois `php database/migrate.php` a cada deploy. Não aplicar mais schema à mão.
- Conexão via `config/database.php` (env `DB_*`); o `exec()` do PDO roda múltiplos comandos por
  arquivo.
