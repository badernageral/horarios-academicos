# SGA – Sistema de Geração Automática de Horários Acadêmicos

Aplicação web para o IFTO que gera grades horárias automaticamente. PHP 8.3+ com MVC próprio
(sem Composer/frameworks), MySQL, Bootstrap 5.3 via CDN. Horários em TIME real
(`hora_inicio`/`hora_fim`), sem períodos fixos no banco.

## ⚠️ Regras críticas

- **O banco contém DADOS REAIS** (professores, disciplinas e atribuições do campus).
  NUNCA inserir dados fictícios nem remover registros sem confirmação explícita do usuário.
  Para testar o gerador sem gravar: replicar `gerar()` via Reflection pulando `salvar()` (só SELECTs).
- **mbstring NÃO está instalado**: proibido `mb_*`. Para UTF-8 usar PCRE com flag `/u`
  (ex.: `preg_match('/^.{0,50}/us', ...)` para truncar sem quebrar acentos).
- Toda mudança de schema deve ser aplicada no banco vivo **e** em `database/schema.sql`.

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

- **Limbo**: `horarios.dia_semana = 0` = disciplina sem horário (zona de limbo por turma na
  grade, drag & drop). Excluído de exportações (`semLimbo()`) e da API stats; presente em
  `porGeracao()` (a grade precisa).
- Cada geração SUBSTITUI a anterior (`DELETE FROM geracoes WHERE semestre_id`) — decisão do
  usuário: Regerar = reset intencional. Não exibir "Geração #id" na UI (sem significado).
- Grade (`app/Views/horarios/grade.php`): drag & drop com swap (soltar sobre célula ocupada
  troca — `POST /horarios/geracao/trocar`), Desfazer via sessionStorage (`sgaUndo`; mover
  retorna `anterior`), filtros curso/turma/professor/sala (filtro ativo = somente leitura,
  sem JS de drag), relatório vivo da grade (painel persiste aberto via localStorage),
  impressão por turma (1/página, `@media print`) e "todos por professor"
  (`/horarios/geracao/{id}/imprimir/professores`), verificador de conflitos para aluno
  multi-período (`POST .../conflitos`, nomes 1/linha).
- `FeasibilityChecker::verificar(semestreId)` roda em `/horarios` e na tela de atribuição:
  encontro maior que os slots do dia, demanda semanal > capacidade da turma, professor sem
  disponibilidade.
- Cores: blocos com fundo = cor primária do professor + sufixo alpha `59` (~35%), texto preto,
  faixa inferior na cor secundária com o nome do professor em branco.
- Backup: botão no Dashboard → `GET /horarios/backup` (mysqldump → `backups/` + download).
- Clonar atribuições entre semestres: dropdown em `/horarios` (aparece com 2+ semestres).

## Decisões do usuário (não sugerir de novo)

- Sem capacidade de sala. Sábado não é letivo (grade renderiza só
  seg–sex). NDAs mantidos (ajudam a visualizar professores). Disponibilidade 07:00–23:00 de
  todos os professores é intencional. Sem histórico de gerações.

## Autenticação (jun/2026)

Login por sessão com **perfil único** (acesso total; sem papéis/permissões). Tabela `usuarios`
(`senha_hash` = `password_hash`/bcrypt), usuário padrão **admin/admin**. `App\Core\Auth`
(`check/user/id/login/logout`); guarda em `index.php` antes do `dispatch` redireciona tudo para
`/login`, exceto a rota `/login`. Tela de login é standalone (`auth/login.php` via
`View::renderPartial`, sem o layout/sidebar). CRUD em `/usuarios` (não pode excluir/desativar a
si mesmo nem deixar o sistema sem usuários). Logout em `/logout`.
