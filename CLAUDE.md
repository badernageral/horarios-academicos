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
- `app/Services/` — ScheduleGenerator, TimeHelper (min↔TIME), Exporter, FeasibilityChecker,
  GradeLayout (malha de slots — fonte única compartilhada pela view e pelo PDF), PdfExporter
- `lib/fpdf/` — FPDF 1.86 vendorizado (PHP puro, licença permissiva), único uso: PdfExporter
- `config/database.php` — credenciais (env vars com fallback)
- Servidor dev: `php -S 127.0.0.1:8080 -t public`

## Fluxo principal

Curso (turno, intervalos, duração de aula) → Turmas → Disciplinas (`qtd_encontros_semanais`,
`qtd_aulas` por encontro, `qtd_professores`, `semestre_oferta` bitmask) → Professores
(disponibilidade por turno, 2 cores únicas da paleta de 50) → Semestre → Atribuição
(`semestre_atribuicoes` com `slot` para múltiplos professores) → Gerar → Grade.

## ScheduleGenerator (app/Services/ScheduleGenerator.php)

Fases: carregar → criar atividades (1 por encontro; encontros divididos entre slots de
professores) → ordenação MCF (menos dias, maior duração, professor mais carregado) → greedy →
busca local (swap de falhas) → `otimizar()` (hill climbing) → `garantirDoisDias()` → salvar.

- **Slots**: `slotsAula()` gera a MESMA malha da grade visual (passos de `duracao_aula_minutos`
  pulando intervalos). Encontros podem ATRAVESSAR intervalos (ex.: 6 aulas = 07:00–11:50 com
  break embutido; `hora_fim` inclui o intervalo). `GradeLayout` gera **um bloco por linha de
  horário** (rowspan sempre 1): cada linha repete disciplina, o horário daquela linha e o
  professor — decisão do usuário, para não sobrar vazio entre o nome e a faixa em blocos altos.
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
- Grade (`app/Views/horarios/grade.php`): **linhas de horário com altura fixa**
  (`tr.slot-row` 70px na tela / 44px no papel; `tr.intervalo-row` menor), para um bloco de N
  aulas ficar N vezes maior que um de 1 aula — sem isso a linha se ajusta ao conteúdo e um
  bloco de 2 aulas fica do tamanho de um de 1. O `.disc-block` é posicionado sobre a célula
  (`position:absolute`; altura percentual não resolve dentro de `<td>`). Drag & drop com swap (soltar sobre célula ocupada
  troca — `POST /horarios/geracao/trocar`), Desfazer via sessionStorage (`sgaUndo`; mover
  retorna `anterior`), filtros curso/turma/professor/sala (filtro ativo = somente leitura,
  sem JS de drag), relatório vivo da grade (painel persiste aberto via localStorage),
  impressão por turma (1/página, `@media print`; modal `#modalImprimir` escolhe as turmas
  por checkbox — o JS **retira do DOM** as não selecionadas antes de `print()` e as devolve
  no `afterprint`, deixando marcadores de comentário no lugar. Esconder com `display:none`
  NÃO serve: o bloco oculto ainda conta para `.turma-bloco + .turma-bloco { break-before }`
  em alguns navegadores e sai folha em branco. O bloco `@media print` também **compacta**
  a tabela com `!important` e aplica `zoom: 0.85` (afeta o layout no Firefox 126+/Chrome;
  `0.75` em retrato, via classe `print-retrato` no `<html>`) — o modal tem seletor de
  orientação, que reescreve o `<style id="regra-pagina">` (`@page` não aceita seletor); o
  botão **Exportar PDF** abre o MESMO modal (`data-modo="pdf"`) mas baixa o arquivo gerado no
  servidor: `GET /horarios/geracao/{id}/exportar/pdf?turmas=1,2&orientacao=landscape|portrait`
  → `PdfExporter` (FPDF vendorizado em `lib/fpdf`, PHP puro, sem Composer; `lib/` bloqueada
  por `.htaccess` e pelo `router.php`). A conversão UTF-8→CP1252 é feita à mão no
  `PdfExporter::txt()` (mbstring é proibido e o PHP do desktop não habilita iconv). A
  exportação CSV foi retirada só da grade, segue em `gerar.php`/`detalhe.php` —
  sem folga, a turma mais alta passa da área útil (impressora real impõe margens > 8mm do
  `@page`) e o cabeçalho repetido cai numa 2ª página que parece vazia) e "todos por professor"
  (`/horarios/geracao/{id}/imprimir/professores`), verificador de conflitos para aluno
  multi-período (`POST .../conflitos`, nomes 1/linha).
- `FeasibilityChecker::verificar(semestreId)` roda em `/horarios` e na tela de atribuição:
  encontro maior que os slots do dia, demanda semanal > capacidade da turma, professor sem
  disponibilidade.
- Cores: blocos com fundo = cor primária do professor + sufixo alpha `59` (~35%), faixa inferior
  na cor secundária com o nome do professor. A cor do texto NÃO é fixa: sai de
  `ColorHelper::textoSobre()` (luminância relativa WCAG), que devolve `#000` ou `#fff` — o que
  contrastar mais. O corpo do bloco é medido com a MESMA opacidade do fundo (`alpha 0.35`,
  composto sobre branco), senão a medição não corresponde ao que se vê; a faixa é medida na
  secundária cheia. Branco fixo na faixa sumia em secundárias claras (âmbar, lima, índigo
  claro). Mesma regra no PDF (`PdfExporter`) e na listagem de professores, onde o fundo é a
  primária CHEIA (sem alpha) — por isso lá o texto alterna preto/branco com frequência.
- **Unicidade de cor é POR NDA, não global** (a paleta tem 50 pares; com ~100 professores não
  fecha globalmente, e o que se compara na grade são professores do mesmo NDA). O CRUD de
  professores lista **agrupado por NDA**, cada grupo com um botão *Corrigir cores*
  (`POST /professores/corrigir-cores` com `nda_id`; `nda_id=0` = sem NDA). Ele só reatribui os
  DUPLICADOS — a 1ª ocorrência (menor id) mantém a cor, para não perder a referência visual de
  quem já conhece o professor pela cor — e prefere pares cuja cor PRIMÁRIA ainda esteja livre,
  já que é ela que pinta o bloco. Cor fora da paleta é preservada se for única no grupo.
- Backup: item de menu `/backup` (`BackupController`). Exportar = `GET /backup/exportar`
  (`VACUUM INTO` → `.sqlite` em `backups/` + download). Importar = `POST /backup/importar`
  (upload `.sqlite`, valida `integrity_check`, salva `pre_import_*` e substitui o arquivo do
  banco, removendo `-wal`/`-shm` antigos). Tudo via PHP/pdo_sqlite, sem binários externos.
- Copiar semestre: dropdown em `/horarios` (aparece com 2+ semestres) — leva atribuições
  **e a grade** (`geracoes` + `horarios`, contadores recalculados), ambas filtradas pela
  oferta do destino (`d.semestre_oferta & destino.semestre`). A grade do destino é sempre
  substituída (mesmo se a origem não tiver grade), para não sobrar horário apontando para
  atribuição trocada. Redireciona para a grade copiada.

## Disponibilidade do professor por turno (ago/2026)

O cadastro do professor NÃO tem mais faixas de hora: é uma grade de **3 turnos × 5 dias**
(15 retângulos) e cada um cicla em 3 estados ao clique — decisão do usuário.

- `disponibilidade_professor` guarda `(professor_id, dia_semana, turno, estado)` — só o NOME
  do turno, nenhuma hora. As faixas ficam na tabela `turnos`, editáveis em **`/configuracoes`**
  (`ConfiguracoesController` + `Turno::todos()/salvar()`); `config/app.php` (`turnos`) virou só
  fallback/semente para banco sem a migration 002. As faixas devem ser CONTÍGUAS (07:00–12:00 /
  12:00–18:00 / 18:00–23:00): um vão vira zona morta onde nada pode ser agendado — a tela avisa
  sobre vãos e sobreposições, mas não bloqueia o salvamento.
- Na tela de configuração **só as horas** são editáveis. Chave e nome do turno são fixos: a
  chave é referenciada por `disponibilidade_professor.turno`, e renomear/remover deixaria
  linhas órfãs.
- `estado = 1` verde (pode) · `estado = 2` interrogação (só se não houver verde viável) ·
  **vermelho = ausência de linha** (é assim que "não pode" sempre foi representado).
- No gerador, `preferenciaProfessor()` devolve `null|1|2` num lookup `[prof][dia][turno]`.
  Uma aula pode ATRAVESSAR turnos (ex.: 6 aulas seguidas): todos os turnos tocados precisam
  estar liberados, e basta um amarelo para a aula inteira contar como amarela.
- O amarelo é implementado como peso soft `turno_reserva` (50000), deliberadamente maior que a
  soma dos demais softs no pior caso (~25k), para que um verde SEMPRE vença um amarelo na
  escolha de uma mesma aula. Continua soft: sem verde viável, o amarelo é usado. Como o
  agendamento é guloso, isso é preferência forte, **não garantia global** — outra atividade
  pode ter tomado o verde antes.
- Migrations: `001_disponibilidade_por_turno.sql` recria a tabela e deixa todos os professores
  existentes com os 15 retângulos VERDES (estado de fato do sistema antes da mudança);
  `002_turnos_configuraveis.sql` cria a tabela `turnos` semeada com as três faixas padrão.
- Mudar as faixas NÃO altera grades já geradas (os `horarios` têm hora própria), mas muda o
  significado do que cada professor marcou — vale regerar depois.

## Lacunas da turma (ago/2026)

Turmas também têm turnos bloqueados — ex.: "1A não tem aula na quarta à tarde". Mesma grade
3 turnos × 5 dias do professor, mas com **dois estados** (pode / não pode).

- `disponibilidade_turma` guarda `(turma_id, dia_semana, turno)`. **Presença = pode ter aula;
  AUSÊNCIA = bloqueado** — mesma convenção de `disponibilidade_professor`, para não haver duas
  regras diferentes. Sem coluna de estado: só há dois casos.
- No gerador, `turmaLiberada()` é restrição DURA, checada antes da do professor (corta candidatos
  mais cedo). Uma aula que atravessa turnos exige todos eles liberados. `getDiasDisponiveis()`
  também intersecta os dias da turma, para a ordenação MCF refletir a restrição real.
- Sem espaço nos turnos liberados, a disciplina vai para o **limbo** — decisão do usuário.
- `FeasibilityChecker` desconta as lacunas ao calcular a capacidade da turma
  (`capacidadeTurma()`) e avisa quando a turma não tem nenhum turno liberado.
- Migration `003_disponibilidade_turma.sql` libera os 15 turnos de toda turma existente
  (comportamento anterior). Turma nova ou importada também nasce liberada em tudo.

## Anotações na grade (ago/2026)

Anotações livres para horários pontuais (ex.: "Reposição do dia 10/02"), presas à GERAÇÃO —
somem quando ela é substituída, que é o desejado para uma semana específica.

- **No bloco**: coluna `horarios.observacao`. Fica na própria linha, então acompanha o bloco
  quando ele é arrastado. Uma aula de N tempos vira N blocos com o MESMO `data-horario-id`:
  o JS precisa pintar/limpar a anotação em TODOS eles.
- **No nome da turma**: tabela `anotacoes_turma (geracao_id, turma_id, texto)`, com CASCADE em
  `geracoes` — não há linha única de turma dentro da geração.
- UI: duplo clique no bloco, lápis no cabeçalho da turma; ambos abrem `#modalAnotacao`.
  **Salvar com texto vazio REMOVE** — é a forma de apagar. `POST /horarios/geracao/anotar`.
- Aparecem na grade, na impressão, no PDF (por turma e nas agendas de professor/sala).
  No PDF do bloco a posição vem do `GetY()` pós-MultiCell, porque o nome da disciplina pode
  ocupar mais de uma linha; um deslocamento fixo não cabia na altura de um slot.
- Truncadas em 200 caracteres com PCRE `/us` (mbstring é proibido no projeto).

## Exportação unificada da grade (ago/2026)

Um botão **Exportar** → escopo (turma | professor | sala) → modal com orientação, seleção de
turmas (só no escopo turma) e três botões: PDF, PNG, Imprimir.

- **Turma** usa a malha do `GradeLayout`. **Professor e sala** usam layout de AGENDA
  (`imprimir_agenda.php` + `PdfExporter::gerarAgenda()`), porque `GradeLayout` amarra turno e
  duração de aula ao CURSO da turma e um professor atravessa cursos com turnos diferentes.
- **PNG é gerado no navegador** (html2canvas vendorizado em `assets/vendor/`): rasterizar no
  servidor exigiria imagick, que o PHP embutido do app desktop não tem.
- Professor/sala abrem a página de agenda com `?acao=imprimir|png`, que se encarrega sozinha.
- Bootstrap 5 não tem submenu aninhado: por isso o formato são botões no rodapé do modal, e
  não um segundo nível de menu.

## Decisões do usuário (não sugerir de novo)

- Sem capacidade de sala. Sábado não é letivo (grade renderiza só
  seg–sex). NDAs mantidos (ajudam a visualizar professores). Sem histórico de gerações.

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
- **Web: aplicação automática.** `index.php` chama `Migrator::verificarNoBoot()` em TODA
  requisição (não só no login — quem já tem sessão aberta passaria direto e rodaria código novo
  contra schema velho). Custo normal é um `filemtime()` no diretório de migrations, comparado
  com um carimbo na sessão; só quando a data muda é que consulta o banco.
- `database/install.php` faz **baseline** ao criar o banco (igual ao `main.js` do desktop):
  banco novo nasce no estado do `schema.sql`, então as migrations são marcadas sem executar.
  Sem isso o banco ficava fora do controle e a atualização automática se recusava a agir.
- **Trava de segurança:** só aplica se `schema_migrations` EXISTIR. Num banco criado do
  `schema.sql` sem baseline, aplicar às cegas é destrutivo — a 001 faz
  `DROP TABLE disponibilidade_professor`. Nesse caso a tela de login pede
  o comando correto para o caso, e nada é alterado: banco já no schema atual precisa de
  `baseline` (marcar sem executar); banco antigo precisa de `up` (executar de fato). A
  distinção sai de `Migrator::schemaAtual()` — sugerir o errado deixaria o schema desalinhado.
- Migration que falha derruba a requisição com uma página 500 explicando o erro: schema
  desalinhado quebraria em SQL no meio de qualquer tela.
- `Migrator::aplicar()` usa lock de arquivo — dois acessos simultâneos não aplicam a mesma
  migration duas vezes. O CLI (`migrate.php up`) delega ao MESMO `Migrator`.
- **Servidor de produção (fazer 1× em banco legado):** `php database/migrate.php baseline`.
- Conexão via `config/database.php` (env `DB_*`); o `exec()` do PDO roda múltiplos comandos por
  arquivo.
